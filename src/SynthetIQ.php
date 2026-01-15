<?php

namespace BlueFission\SynthetIQ;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Language\IInterpreter;
use BlueFission\SynthetIQ\ConversationHistory;
use BlueFission\SynthetIQ\Intents\Classifier;
use BlueFission\SynthetIQ\Responses\Generator;
use BlueFission\SynthetIQ\Responses\Selector;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Intent\Matcher;
use BlueFission\Automata\Language\ContractionNormalizer;
use BlueFission\Automata\Language\TrigramMarkovPredictor;
use BlueFission\SynthetIQ\Models\LearningModel;
use BlueFission\SynthetIQ\Memory\MemoryAdapterInterface;
use BlueFission\SynthetIQ\Memory\NullMemoryAdapter;
use BlueFission\SynthetIQ\Memory\MemoryRecall;
use BlueFission\SynthetIQ\Fallback\FallbackResponderInterface;
use BlueFission\SynthetIQ\Fallback\NullFallbackResponder;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\DevElation as Dev;

class SynthetIQ
{
    protected $_context;
    protected $_history;
    protected $_interpreter;
    protected $_intentClassifier;
    protected $_responseGenerator;
    protected $_responseSelector;
    protected $_predictor;
    protected $_matcher;
    protected $_routes;
    protected $_input = '';
    protected $_learningModel;
    protected $_memoryAdapter;
    protected $_fallbackResponder;
    protected $_confidenceThreshold = 0.35;

    public function __construct(
        IInterpreter $interpreter,
        IAnalyzer $analyzer,
        ?LearningModel $learningModel = null,
        ?MemoryAdapterInterface $memoryAdapter = null,
        ?FallbackResponderInterface $fallbackResponder = null,
        ?float $confidenceThreshold = null
    )
    {
        $this->_context = new Context();
        $this->_history = new ConversationHistory();
        $this->_intentClassifier = new Classifier( $analyzer );
        $this->_responseGenerator = new Generator();
        $this->_matcher = new Matcher($analyzer);
        $this->_predictor = new TrigramMarkovPredictor();
        $this->_routes = [];
        $this->_interpreter = $interpreter;
        $this->_learningModel = $learningModel ?? new LearningModel();
        $this->_memoryAdapter = $memoryAdapter ?? new NullMemoryAdapter();
        $this->_fallbackResponder = $fallbackResponder ?? new NullFallbackResponder();
        if ($confidenceThreshold !== null) {
            $this->_confidenceThreshold = $confidenceThreshold;
        }
     
        $this->_responseSelector = new Selector($this->_predictor, [$this, 'evaluateNode']);
    }

    public function setMemoryAdapter(MemoryAdapterInterface $adapter): void
    {
        $this->_memoryAdapter = $adapter;
    }

    public function setFallbackResponder(?FallbackResponderInterface $responder): void
    {
        $this->_fallbackResponder = $responder ?? new NullFallbackResponder();
    }

    public function setConfidenceThreshold(?float $threshold): void
    {
        if ($threshold === null) {
            $this->_confidenceThreshold = null;
            return;
        }

        $this->_confidenceThreshold = $threshold;
    }

    public function processInput(string $input): string
    {
        $input = ContractionNormalizer::normalize($input);
        // Run the input through the interpreter, it will produce an output
        $this->_interpreter->run(Str::lower($input));

        $memoryRecall = $this->recallMemory($input);

        $scores = $this->_intentClassifier->score($input, $this->_context);
        if ($memoryRecall) {
            $scores = $this->applyIntentBiases($scores, $memoryRecall->intentBiases());
        }

        $intent = $this->_intentClassifier->classifyFromScores($input, $this->_context, $scores);
        $confidence = $this->computeConfidence($scores);

        $this->_context->set('intent_scores', $scores ? $scores->toArray() : []);
        $this->_context->set('intent_confidence', $confidence);
        Dev::do('synthetiq.intent.scored', [
            'input' => $input,
            'scores' => $scores ? $scores->toArray() : [],
            'confidence' => $confidence,
        ]);

        if ( !$intent ) {
            // If the intent is not recognized, use the last intent
            $intent = $this->_context->get('last_intent') ?? new Intent('unknown.intent', 'Unknown');
        }
        $this->_context->set('current_intent', $intent);

        $fallbackResponse = $this->maybeRunFallback($input, $intent, $scores, $confidence, false);
        if ($fallbackResponse !== null) {
            $response = $fallbackResponse;
            $this->_history->addEntry($input, $response);
            $this->_context->set('last_intent', $intent);
            if ($this->_learningModel) {
                $this->_learningModel->observe($input, $response, $this->_context);
            }
            $this->recordMemory($input, $response);

            return $response;
        }

        $responseTypes = $this->_routes[$intent->getLabel()] ?? [];
        if (empty($responseTypes)) {
            $responseTypes = [$intent->getLabel()];
        } elseif (!is_array($responseTypes)) {
            $responseTypes = [$responseTypes];
        }
        $responses = [];

        foreach ($responseTypes as $responseType) {
            $responseIntent = $this->_matcher->getIntent($responseType);
            if (!$responseIntent) {
                continue;
            }

            $value = $this->_responseGenerator->generate($input, $responseIntent, $this->_context);
            if ($value === '') {
                continue;
            }

            $responses[$value] = $value;
            // var_dump($responseType, $intent, $value);
        }

        if (empty($responses) && $intent->getLabel() !== 'unknown.intent') {
            $responseTypes = ['unknown.intent'];
            $responseIntent = $this->_matcher->getIntent('unknown.intent');
            if ($responseIntent) {
                $value = $this->_responseGenerator->generate($input, $responseIntent, $this->_context);
                if ($value !== '') {
                    $responses[$value] = $value;
                }
            }
        }

        $this->_context->set('expected_intents', $responseTypes);

        $this->_input = $input;
        $response = '';
        if (!empty($responses)) {
            // Use selector scoring to choose a consistent response.
            $response = $this->_responseSelector->select($input, $responses, $this->_context);
        }
        $this->_input = '';

        if ($response === '' && $this->_learningModel) {
            $response = $this->_learningModel->generate($input, $this->_context);
        }

        $fallbackResponse = $this->maybeRunFallback($input, $intent, $scores, $confidence, true);
        if ($fallbackResponse !== null) {
            $response = $fallbackResponse;
        }

        $this->_history->addEntry($input, $response);
        $this->_context->set('last_intent', $intent);
        if ($this->_learningModel) {
            $this->_learningModel->observe($input, $response, $this->_context);
        }
        $this->recordMemory($input, $response);

        return $response;
    }

    public function addRoute($statement, $type, $to = []) {
        if (!is_array($to)) {
            $to = [$to];
        }

        $intent = $this->_matcher->getIntent($type);
        if ( !$intent ) {
            $intent = new Intent($type, $type);
            $this->_matcher->registerIntent($intent);
        }

        $priority = $this->computePriority($statement, 10);
        $intent->addCriteria('keywords', ['word' => $statement, 'priority' => $priority]);
        $this->_responseGenerator->addTemplate($type, $statement);
        
        // foreach ($to as $category) {
        //     $this->_responseGenerator->addTemplate($category, $statement);
        // }

        if (!isset($this->_routes[$type])) {
            $this->_routes[$type] = $to;
        } elseif (!empty($to)) {
            $this->_routes[$type] = Arr::merge($this->_routes[$type], $to);
        }

        if ($statement !== '') {
            $this->_predictor->addSentence($statement);
        }
    }

    public function addIntentKeywords(string $type, array $keywords, ?int $priorityBase = null): void
    {
        if (empty($keywords)) {
            return;
        }

        $intent = $this->_matcher->getIntent($type);
        if (!$intent) {
            $intent = new Intent($type, $type);
            $this->_matcher->registerIntent($intent);
        }

        foreach ($keywords as $keyword) {
            $keyword = Str::trim((string)$keyword);
            if ($keyword === '') {
                continue;
            }

            $priority = $this->computePriority($keyword, $priorityBase ?? 12);
            $intent->addCriteria('keywords', ['word' => $keyword, 'priority' => $priority]);
        }
    }

    protected function computePriority(string $text, int $base): float
    {
        $priority = (float)$base;
        $length = Str::len($text);
        $priority -= ($length / ($base / 2)) ?? $priority;

        return $priority;
    }

    protected function applyIntentBiases(?Arr $scores, array $biases): ?Arr
    {
        if (!$scores instanceof Arr || empty($biases)) {
            return $scores;
        }

        $updated = $scores->toArray();
        foreach ($biases as $label => $weight) {
            if (!is_numeric($weight)) {
                continue;
            }
            $updated[$label] = ($updated[$label] ?? 0.0) + (float)$weight;
        }

        if (!empty($updated)) {
            arsort($updated);
        }

        return Arr::make($updated);
    }

    protected function computeConfidence(?Arr $scores): float
    {
        if (!$scores instanceof Arr || $scores->count() === 0) {
            return 0.0;
        }

        $values = array_values($scores->toArray());
        $topScore = (float)($values[0] ?? 0.0);
        $secondScore = (float)($values[1] ?? 0.0);

        if ($topScore <= 0.0) {
            return 0.0;
        }

        $denom = $topScore + $secondScore;
        return $denom > 0.0 ? ($topScore / $denom) : 0.0;
    }

    protected function determineFallbackReason(?Intent $intent, ?Arr $scores, float $confidence, bool $allowLowConfidence): ?string
    {
        if (!$intent) {
            return 'no_intent';
        }

        if ($intent->getLabel() === 'unknown.intent') {
            return 'unknown_intent';
        }

        if (!$scores instanceof Arr || $scores->count() === 0) {
            return 'no_scores';
        }

        if ($allowLowConfidence && $this->_confidenceThreshold !== null && $confidence < $this->_confidenceThreshold) {
            return 'low_confidence';
        }

        return null;
    }

    protected function maybeRunFallback(string $input, ?Intent $intent, ?Arr $scores, float $confidence, bool $allowLowConfidence): ?string
    {
        if (!$this->_fallbackResponder) {
            return null;
        }

        $reason = $this->determineFallbackReason($intent, $scores, $confidence, $allowLowConfidence);
        if (!$reason) {
            return null;
        }

        $meta = [
            'reason' => $reason,
            'confidence' => $confidence,
            'scores' => $scores ? $scores->toArray() : [],
            'intent' => $intent ? $intent->getLabel() : null,
            'stage' => $allowLowConfidence ? 'post' : 'pre',
        ];

        $response = $this->_fallbackResponder->respond($input, $this->_context, $meta);

        if (is_string($response) && $response !== '') {
            $this->_context->set('fallback_used', true);
            $this->_context->set('fallback_reason', $reason);
            Dev::do('synthetiq.fallback.triggered', $meta);

            return $response;
        }

        return null;
    }

    protected function recallMemory(string $input): ?MemoryRecall
    {
        if (!$this->_memoryAdapter instanceof MemoryAdapterInterface) {
            return null;
        }

        $meta = $this->buildMemoryMeta();
        $recall = $this->_memoryAdapter->recall($input, $this->_context, $meta);
        if (!$recall->isEmpty()) {
            $this->_context->set('memory_recall', $recall->toArray());
        }

        return $recall;
    }

    protected function recordMemory(string $input, string $response): void
    {
        if (!$this->_memoryAdapter instanceof MemoryAdapterInterface) {
            return;
        }

        $meta = $this->buildMemoryMeta();
        $this->_memoryAdapter->recordExchange($input, $response, $this->_context, $meta);
    }

    protected function buildMemoryMeta(): array
    {
        $meta = [];
        $scope = $this->_context->get('memory_scope');
        if ($scope !== null) {
            $meta['scope'] = $scope;
        }
        $userId = $this->_context->get('user_id');
        if ($userId !== null) {
            $meta['user_id'] = $userId;
        }
        $sessionId = $this->_context->get('session_id');
        if ($sessionId !== null) {
            $meta['session_id'] = $sessionId;
        }

        return $meta;
    }

    public function evaluateNode(array $node): int
    {
        $score = 0;
        // echo $node['response'] . "\n";
        $expectedIntents = $this->_context->get('expected_intents') ?? [];
        if (!is_array($expectedIntents)) {
            $expectedIntents = [$expectedIntents];
        }

        // Check if the intent of the phrase is the same
        $intent = $this->_intentClassifier->classify($node['response'], $this->_context);
        if ($intent && in_array($intent->getLabel(), $expectedIntents, true)) {
            // echo "-- Intent match\n";
            $score += 3;
        }

        // Run in interpreter to see if it's valid
        if ( $this->_interpreter->isValid($node['response']) ) {
            // echo "-- Interpreter match\n";
            $score += 2;

            if (method_exists($this->_interpreter, 'tokenize') && method_exists($this->_interpreter, 'parse')) {
                $tokens = $this->_interpreter->tokenize($node['response']);
                $output = $this->_interpreter->parse($tokens);
            }

            // var_dump($output);
        } else {
            // echo "-- Interpreter failed\n";
        }

        // Check levenstein distance
        $distance = levenshtein($node['response'], $this->_input);
        if ($distance < 2) {
            // do nothing
            // echo "-- Levenstein match too high!\n";
            $score -= 2;
        } elseif ($distance < 7) {
            // echo "-- Levenstein match\n";
            $score += 2;
        } elseif ($distance < 10) {
            // echo "-- Levenstein close match\n";
            $score++;
        }

        // Word intersection 
        $sharedTokens = Arr::intersect(
            Str::split($node['response'], ' '),
            Str::split($this->_input, ' ')
        );
        if (count($sharedTokens) > 0) {
            // echo "-- Token match\n";
            $score += 3;
        }

        // Short length penalty
        $length = Str::len($node['response']);
        $penalty = 10 - max(0, $length - 10);
        $score -= round($penalty / 2);

        // echo "\n";

        return $score;
    }
}

