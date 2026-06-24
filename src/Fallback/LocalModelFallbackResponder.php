<?php

namespace BlueFission\SynthetIQ\Fallback;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;
use BlueFission\Str;
use BlueFission\Val;

class LocalModelFallbackResponder implements FallbackResponderInterface
{
    protected FallbackProviderInterface $provider;
    protected TrainingCandidateStore $candidates;
    protected bool $enabled = false;
    protected string $promptPrefix = '';

    public function __construct(
        FallbackProviderInterface $provider,
        ?TrainingCandidateStore $candidates = null,
        array $options = []
    ) {
        $this->provider = $provider;
        $this->candidates = $candidates ?? new TrainingCandidateStore();

        if (isset($options['enabled'])) {
            $this->enabled = (bool)$options['enabled'];
        }
        if (Str::is($options['prompt_prefix'] ?? null)) {
            $this->promptPrefix = (string)$options['prompt_prefix'];
        }
    }

    public function enable(bool $enabled = true): void
    {
        $this->enabled = $enabled;
    }

    public function candidates(): TrainingCandidateStore
    {
        return $this->candidates;
    }

    public function respond(string $input, Context $context, array $meta = []): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $prompt = $this->buildPrompt($input, $meta);
        $response = $this->provider->complete($prompt, $context, $meta);
        if (!Str::is($response) || Val::isEmpty($response)) {
            return null;
        }

        $candidate = $this->candidates->capture([
            'prompt' => $prompt,
            'response' => $response,
            'input' => $input,
            'reason' => $meta['reason'] ?? null,
            'confidence' => $meta['confidence'] ?? null,
            'intent' => $meta['intent'] ?? null,
            'scores' => Arr::is($meta['scores'] ?? null) ? $meta['scores'] : [],
            'stage' => $meta['stage'] ?? null,
            'meta' => $meta,
        ]);

        $context->set('fallback_candidate', $candidate);
        Dev::do('synthetiq.fallback.training_candidate.captured', $candidate);

        return $response;
    }

    protected function buildPrompt(string $input, array $meta): string
    {
        $parts = [];
        if (Val::isNotEmpty($this->promptPrefix)) {
            $parts[] = $this->promptPrefix;
        }

        $parts[] = 'Input: ' . $input;

        if (Str::is($meta['intent'] ?? null)) {
            $parts[] = 'Intent: ' . $meta['intent'];
        }
        if (Str::is($meta['reason'] ?? null)) {
            $parts[] = 'Fallback reason: ' . $meta['reason'];
        }

        return implode("\n", $parts);
    }
}
