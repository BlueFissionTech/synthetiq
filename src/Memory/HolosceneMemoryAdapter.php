<?php

namespace BlueFission\SynthetIQ\Memory;

use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\Context;
use BlueFission\Automata\Language\Reader;
use BlueFission\Automata\Memory\IWorkingMemory;
use BlueFission\Automata\Intent\Intent;
use BlueFission\DevElation as Dev;
use BlueFission\Collections\Collection;
use BlueFission\Str;

class HolosceneMemoryAdapter implements MemoryAdapterInterface
{
    protected IWorkingMemory $memory;
    protected Holoscene $holoscene;
    protected ?Reader $reader;
    protected float $similarityThreshold = 0.4;
    protected int $maxRelated = 5;
    protected float $biasWeight = 1.0;
    protected string $defaultScope = 'global';
    protected $permissionGuard;
    protected array $lastEpisodeByScope = [];

    public function __construct(IWorkingMemory $memory, ?Holoscene $holoscene = null, ?Reader $reader = null, array $options = [])
    {
        $this->memory = $memory;
        $this->holoscene = $holoscene ?? new Holoscene();
        $this->reader = $reader;

        if (isset($options['similarity_threshold'])) {
            $this->similarityThreshold = (float)$options['similarity_threshold'];
        }
        if (isset($options['max_related'])) {
            $this->maxRelated = (int)$options['max_related'];
        }
        if (isset($options['bias_weight'])) {
            $this->biasWeight = (float)$options['bias_weight'];
        }
        if (isset($options['default_scope'])) {
            $this->defaultScope = (string)$options['default_scope'];
        }
        if (isset($options['permission_guard']) && is_callable($options['permission_guard'])) {
            $this->permissionGuard = $options['permission_guard'];
        }
    }

    public function recordExchange(string $input, string $response, Context $context, array $meta = []): void
    {
        $scope = $this->resolveScope($context, $meta);
        if (!$this->isPermitted('write', $scope, $context, $meta)) {
            return;
        }

        $episodeId = $meta['episode_id'] ?? uniqid('episode_', true);
        $label = $scope . ':' . $episodeId;

        $memoryContext = $this->buildMemoryContext($input, $response, $context, $scope, $meta);
        $this->memory->addMemory($label, $memoryContext, $meta['edges'] ?? []);

        $lastLabel = $this->lastEpisodeByScope[$scope] ?? null;
        if ($lastLabel) {
            $this->memory->associate($lastLabel, $label, 1.0);
        }
        $this->lastEpisodeByScope[$scope] = $label;

        if ($this->reader) {
            $this->projectToHoloscene($input, $response, $label);
        }

        Dev::do('synthetiq.memory.recorded', [
            'label' => $label,
            'scope' => $scope,
        ]);
    }

    public function recall(string $input, Context $context, array $meta = []): MemoryRecall
    {
        $scope = $this->resolveScope($context, $meta);
        if (!$this->isPermitted('read', $scope, $context, $meta)) {
            return new MemoryRecall();
        }

        if (!method_exists($this->memory, 'recallSimilar')) {
            return new MemoryRecall();
        }

        $query = $this->buildQueryContext($input, $context, $scope, $meta);
        $results = $this->memory->recallSimilar($query, $this->similarityThreshold);

        if ($this->maxRelated > 0) {
            $limited = [];
            $count = 0;
            (new Collection($results))->each(function ($value, $key) use (&$limited, &$count) {
                if ($count >= $this->maxRelated) {
                    return;
                }
                $limited[$key] = $value;
                $count++;
            });
            $results = $limited;
        }

        $intentBiases = [];

        foreach ($results as $label => $entry) {
            $entryContext = $entry['context'] ?? null;
            if (!$entryContext instanceof Context) {
                continue;
            }

            $intentLabel = $entryContext->get('intent_label');
            if ($intentLabel) {
                $weight = (float)($entry['similarity'] ?? 1.0) * $this->biasWeight;
                $intentBiases[$intentLabel] = ($intentBiases[$intentLabel] ?? 0.0) + $weight;
            }
        }

        if (!empty($intentBiases)) {
            arsort($intentBiases);
        }

        $recall = new MemoryRecall($results, $intentBiases, ['scope' => $scope]);

        Dev::do('synthetiq.memory.recalled', [
            'scope' => $scope,
            'intentBiases' => $intentBiases,
        ]);

        return $recall;
    }

    protected function resolveScope(Context $context, array $meta): string
    {
        $scope = $meta['scope'] ?? $context->get('memory_scope', $this->defaultScope);
        return (string)$scope;
    }

    protected function isPermitted(string $action, string $scope, Context $context, array $meta): bool
    {
        if (!$this->permissionGuard) {
            return true;
        }

        return (bool)call_user_func($this->permissionGuard, $action, $scope, $context, $meta);
    }

    protected function buildMemoryContext(string $input, string $response, Context $context, string $scope, array $meta): Context
    {
        $memoryContext = new Context();
        $memoryContext->set('input', $input);
        $memoryContext->set('response', $response);
        $memoryContext->set('scope', $scope);
        $memoryContext->set('timestamp', $meta['timestamp'] ?? time());

        $intent = $context->get('current_intent');
        if ($intent instanceof Intent) {
            $memoryContext->set('intent_label', $intent->getLabel());
        } elseif (is_string($intent)) {
            $memoryContext->set('intent_label', $intent);
        }

        $confidence = $context->get('intent_confidence');
        if ($confidence !== null) {
            $memoryContext->set('intent_confidence', $confidence);
        }

        if (isset($meta['user_id'])) {
            $memoryContext->set('user_id', $meta['user_id']);
        }
        if (isset($meta['session_id'])) {
            $memoryContext->set('session_id', $meta['session_id']);
        }

        return $memoryContext;
    }

    protected function buildQueryContext(string $input, Context $context, string $scope, array $meta): Context
    {
        $query = new Context();
        $query->set('input', $input);
        $query->set('scope', $scope);

        if (isset($meta['user_id'])) {
            $query->set('user_id', $meta['user_id']);
        }

        return $query;
    }

    protected function projectToHoloscene(string $input, string $response, string $episodeId): void
    {
        if (!$this->reader) {
            return;
        }

        $text = Str::trim($input . ' ' . $response);
        if ($text === '') {
            return;
        }

        try {
            $statements = $this->reader->readDocument($text);
            $this->reader->toHoloscene($statements, $this->holoscene, $this->memory, $episodeId);
        } catch (\Throwable $e) {
            Dev::do('synthetiq.memory.project_failed', ['error' => $e->getMessage()]);
        }
    }
}
