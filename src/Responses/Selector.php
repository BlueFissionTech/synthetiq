<?php

namespace BlueFission\SynthetIQ\Responses;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\DepthFirstMethod;
use BlueFission\Automata\DecisionTree\Node;
use BlueFission\Collections\Collection;
use BlueFission\Str;
use Throwable;

class Selector
{
    protected $_decisionTree;
    protected $_context;
    protected $_predictor;
    protected $_string = [];
    protected $_depth = 0;
    protected $_maxDepth = 15;
    protected $_maxChildren = 5;
    protected $_evalutation;
    protected $_useSingleTokenKey = 0;
    protected $_maxSingleTokenKeyPatterns = 2;
    protected array $_lastDiagnostics = [];

    public function __construct($predictor, $evalutation)
    {
        $this->_decisionTree = new DecisionTree();
        $this->_evalutation = $evalutation;
        $this->_useSingleTokenKey = $this->_maxSingleTokenKeyPatterns;

        $this->_predictor = $predictor;
    }

    public function select(string $input, array $responses, Context $context): string
    {
        $this->_context = $context;

        if (empty($responses)) {
            $this->resetDiagnostics($input, 0);
            $this->markFallback('no_candidates');
            return '';
        }

        $responses = Arr::keys($responses);
        $this->resetDiagnostics($input, count($responses));
        $this->_depth = 0;
        $this->_useSingleTokenKey = $this->_maxSingleTokenKeyPatterns;

        $this->buildDecisionTree($input, $responses);

        $selectedNode = $this->_decisionTree->decide(new DepthFirstMethod());

        // Only return a response that was explicitly provided as a candidate.
        if ($selectedNode && in_array($selectedNode['response'], $responses, true)) {
            return $selectedNode['response'];
        }

        $this->markFallback('selection_miss');

        return (new Collection($responses))->rand();
    }

    public function lastDiagnostics(): array
    {
        return $this->_lastDiagnostics;
    }

    protected function buildDecisionTree($input, $samples): void
    {
        // Example decision tree building logic
        if (empty($samples)) {
            return;
        }

        $defaultResponse = (new Collection($samples))->rand();
        $rootNode = new Node(['response' => $defaultResponse], $this->_evalutation);

        // Get the most likely first words as nodes from the default responses
        $firstWords = (new Collection($samples))
            ->map(function ($response) {
                $tokens = Str::split($response, ' ');
                return $tokens[0] ?? '';
            })
            ->filter(function ($word) {
                return $word !== '';
            })
            ->toArray();

        if (empty($firstWords)) {
            $beginning = $this->predictBeginning();
            if ($beginning !== null) {
                $firstWords[] = $beginning;
            }
        }

        if (empty($firstWords)) 
            return;

        $count = count($firstWords) <= $this->_maxChildren ? count($firstWords) : $this->_maxChildren;

        for($i = 0; $i < $count; $i++) {
            $token = $firstWords[$i];
            $value = $token;
            $newNode = new Node(['response' => $value], $this->_evalutation);
            $this->buildDecisionTreeRecursive($newNode, $token);

            $rootNode->addChild($newNode);
        }

        foreach ($samples as $key => $value) {
            $this->buildDecisionTreeRecursive($rootNode, $value);
        }

        $this->_decisionTree->setRoot($rootNode);
    }

    // recursive function
    protected function buildDecisionTreeRecursive(&$node, $input): void
    {
        $tokens = $this->predictTokens((string)$input);

        if (empty($tokens) && $this->_depth < 8 && $this->_useSingleTokenKey > 0) {
            $this->_useSingleTokenKey--;
            $lastWord = (new Collection(Str::split($input, ' ')))->last();
            $lastWord = (string)($lastWord ?: '');
            $tokens = $this->predictTokens($lastWord);
        }

        // var_dump($input, $tokens);

        if (empty($tokens)) {
            return;
        }

        $count = count($tokens) <= $this->_maxChildren ? count($tokens) : $this->_maxChildren;

        for($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $value = $input . ' ' . $token;
            $newNode = new Node(['response' => $value], $this->_evalutation);
            $node->addChild($newNode);

            if ($this->_depth <= $this->_maxDepth && !preg_match('/[.!?]$/', Str::trim($value)) && Str::len($value) < 100) {
                $this->_depth++;
                $this->buildDecisionTreeRecursive($newNode, $value);
                $this->_depth--;
            }
        }
        $this->_useSingleTokenKey = $this->_maxSingleTokenKeyPatterns;
    }

    public function addNode($statement, $evaluation = null) {
        $evaluation = $evaluation ?? $this->_evalutation;

        $node = new Node(['response' => $statement], $evaluation);
        $this->_decisionTree->getRoot()->addChild($node);
    }

    protected function resetDiagnostics(string $input, int $candidateCount): void
    {
        $status = $this->detectPredictorStatus();
        $this->_lastDiagnostics = [
            'status' => $status,
            'fallback_used' => false,
            'fallback_reason' => null,
            'candidate_count' => $candidateCount,
            'tokens_considered' => 0,
            'input_length' => Str::len($input),
            'error' => null,
        ];

        if ($status === 'disabled' || $status === 'unavailable') {
            $this->markFallback('predictor_' . $status);
        }
    }

    protected function detectPredictorStatus(): string
    {
        if ($this->_predictor === null) {
            return 'disabled';
        }

        if (!is_object($this->_predictor)) {
            return 'unavailable';
        }

        if (
            !method_exists($this->_predictor, 'predictNextWords')
            && !method_exists($this->_predictor, 'predictNextWord')
            && !method_exists($this->_predictor, 'predictBeginning')
        ) {
            return 'unavailable';
        }

        return 'available';
    }

    protected function predictBeginning(): ?string
    {
        if (!is_object($this->_predictor) || !method_exists($this->_predictor, 'predictBeginning')) {
            return null;
        }

        try {
            $beginning = $this->_predictor->predictBeginning();
        } catch (Throwable $e) {
            $this->markPredictorFailure($e);
            return null;
        }

        return is_string($beginning) && $beginning !== '' ? $beginning : null;
    }

    protected function predictTokens(string $input): array
    {
        if (!is_object($this->_predictor)) {
            return [];
        }

        try {
            if (method_exists($this->_predictor, 'predictNextWords')) {
                $tokens = $this->_predictor->predictNextWords($input);
            } elseif (method_exists($this->_predictor, 'predictNextWord')) {
                $tokens = [];
                for ($i = 0; $i < $this->_maxChildren; $i++) {
                    $next = $this->_predictor->predictNextWord($input);
                    if ($next) {
                        $tokens[] = $next;
                    }
                }
            } else {
                return [];
            }
        } catch (Throwable $e) {
            $this->markPredictorFailure($e);
            return [];
        }

        if (!is_array($tokens)) {
            return [];
        }

        $tokens = array_values(Arr::unique(array_filter($tokens, static function ($token): bool {
            return is_string($token) && $token !== '';
        })));

        $this->_lastDiagnostics['tokens_considered'] += count($tokens);

        return $tokens;
    }

    protected function markPredictorFailure(Throwable $e): void
    {
        $this->_lastDiagnostics['status'] = 'failed';
        $this->_lastDiagnostics['error'] = [
            'type' => get_class($e),
            'message' => $e->getMessage(),
        ];
        $this->markFallback('predictor_failed');
    }

    protected function markFallback(string $reason): void
    {
        $this->_lastDiagnostics['fallback_used'] = true;
        if (($this->_lastDiagnostics['fallback_reason'] ?? null) === 'predictor_failed') {
            return;
        }

        $this->_lastDiagnostics['fallback_reason'] = $reason;
    }
}
