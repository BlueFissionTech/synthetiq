<?php

namespace BlueFission\SynthetIQ;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Language\IInterpreter;
use BlueFission\SynthetIQ\ConversationHistory;
use BlueFission\SynthetIQ\Intents\IntelligenceRouter;
use BlueFission\SynthetIQ\Responses\Generator;
use BlueFission\SynthetIQ\Responses\Selector;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Intent\Matcher;
use BlueFission\Automata\Language\ContractionNormalizer;
use BlueFission\SynthetIQ\Models\LearningModel;
use BlueFission\SynthetIQ\Language\BoundedTrigramPredictor;
use BlueFission\SynthetIQ\Language\SpellCorrector;
use BlueFission\SynthetIQ\Memory\MemoryAdapterInterface;
use BlueFission\SynthetIQ\Memory\NullMemoryAdapter;
use BlueFission\SynthetIQ\Memory\MemoryRecall;
use BlueFission\SynthetIQ\Fallback\FallbackResponderInterface;
use BlueFission\SynthetIQ\Fallback\NullFallbackResponder;
use BlueFission\Arr;
use BlueFission\Func;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;
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
    protected $_spellCorrector;
    protected bool $_spellCorrectionEnabled = true;

    public function __construct(
        IInterpreter $interpreter,
        IAnalyzer $analyzer,
        ?LearningModel $learningModel = null,
        ?MemoryAdapterInterface $memoryAdapter = null,
        ?FallbackResponderInterface $fallbackResponder = null,
        ?float $confidenceThreshold = null,
        array $routerOptions = []
    )
    {
        $this->_context = new Context();
        $this->_history = new ConversationHistory();
        $this->_matcher = new Matcher($analyzer);
        $this->_intentClassifier = new IntelligenceRouter($analyzer, $this->_matcher, $routerOptions);
        $this->_responseGenerator = new Generator();
        $this->_predictor = new BoundedTrigramPredictor();
        $this->_routes = [];
        $this->_interpreter = $interpreter;
        $this->_learningModel = $learningModel ?? new LearningModel();
        $this->_memoryAdapter = $memoryAdapter ?? new NullMemoryAdapter();
        $this->_fallbackResponder = $fallbackResponder ?? new NullFallbackResponder();
        $this->_spellCorrector = new SpellCorrector();
        if ($confidenceThreshold !== null) {
            $this->_confidenceThreshold = $confidenceThreshold;
        }

        $this->refreshResponseSelector();
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

    public function enableSpellCorrection(bool $enabled): void
    {
        $this->_spellCorrectionEnabled = $enabled;
        if ($this->_spellCorrector) {
            $this->_spellCorrector->enable($enabled);
        }
    }

    public function setSpellCorrector(?SpellCorrector $corrector): void
    {
        $this->_spellCorrector = $corrector;
        if ($this->_spellCorrector) {
            $this->_spellCorrector->enable($this->_spellCorrectionEnabled);
        }
    }

    public function setResponsePredictor($predictor): void
    {
        $this->_predictor = $predictor;
        $this->refreshResponseSelector();
    }

    public function responsePredictorDiagnostics(): array
    {
        $diagnostics = [];
        if (
            $this->_responseSelector
            && $this->canCall($this->_responseSelector, 'lastDiagnostics')
        ) {
            $diagnostics = $this->_responseSelector->lastDiagnostics();
        }

        if (Val::isEmpty($diagnostics)) {
            return $this->baseResponsePredictorDiagnostics();
        }

        return Arr::merge($this->baseResponsePredictorDiagnostics(), $diagnostics);
    }

    public function processInput(string $input): string
    {
        $rawInput = $input;
        $this->resetTurnDiagnostics($rawInput);

        $input = ContractionNormalizer::normalize($input);
        $input = $this->normalizeInput($input);
        // Run the input through the interpreter, it will produce an output
        $this->_interpreter->run(Str::lower($input));

        $memoryRecall = $this->recallMemory($input);

        $scores = $this->_intentClassifier->score($input, $this->_context);
        if ($memoryRecall) {
            $scores = $this->applyIntentBiases($scores, $memoryRecall->intentBiases());
        }

        $intent = $this->_intentClassifier->classifyFromScores($input, $this->_context, $scores);
        $confidence = $this->computeConfidence($scores);

        $turnScores = $scores ? $scores->toArray() : [];
        $this->_context->set('intent_scores', $turnScores);
        $this->_context->set('selected_intent_scores', $turnScores);
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
        $this->_context->set('selected_intent_label', $intent->getLabel());

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
        if (Val::isEmpty($responseTypes)) {
            $responseTypes = [$intent->getLabel()];
        } elseif (!Arr::is($responseTypes)) {
            $responseTypes = [$responseTypes];
        }
        $responses = [];
        $responseIntentMap = [];

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
            $responseIntentMap[$value] = (string)$responseType;
            // var_dump($responseType, $intent, $value);
        }

        if (Val::isEmpty($responses) && $intent->getLabel() !== 'unknown.intent') {
            $responseTypes = ['unknown.intent'];
            $responseIntent = $this->_matcher->getIntent('unknown.intent');
            if ($responseIntent) {
                $value = $this->_responseGenerator->generate($input, $responseIntent, $this->_context);
                if ($value !== '') {
                    $responses[$value] = $value;
                    $responseIntentMap[$value] = 'unknown.intent';
                }
            }
        }

        $this->_context->set('expected_intents', $responseTypes);

        $this->_input = $input;
        $response = '';
        if (Val::isNotEmpty($responses)) {
            // Use selector scoring to choose a consistent response.
            $response = $this->_responseSelector->select($input, $responses, $this->_context);
            $this->recordSelectedResponseIntent($response, $responseIntentMap, $memoryRecall);
            $this->recordResponsePredictorDiagnostics();
        }
        $this->_input = '';

        if ($response === '' && $this->_learningModel) {
            $response = $this->_learningModel->generate($input, $this->_context);
        }

        $fallbackResponse = $this->maybeRunFallback($input, $intent, $scores, $confidence, true);
        if ($fallbackResponse !== null) {
            $response = $fallbackResponse;
            $this->_context->set('selected_intent_label', $intent->getLabel());
            $this->_context->set('selected_intent_scores', $turnScores);
        }

        $this->_history->addEntry($input, $response);
        $this->_context->set('last_intent', $intent);
        if ($this->_learningModel) {
            $this->_learningModel->observe($input, $response, $this->_context);
        }
        $this->recordMemory($input, $response);

        return $response;
    }

    public function processInputEnvelope(string $input): array
    {
        $response = $this->processInput($input);

        return $this->buildResponseEnvelope($input, $response);
    }

    public function addRoute($statement, $type, $to = []) {
        if (!Arr::is($to)) {
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

        if (!Arr::hasKey($this->_routes, $type)) {
            $this->_routes[$type] = $to;
        } elseif (Arr::count($to) > 0) {
            $existingRoutes = Arr::is($this->_routes[$type]) ? $this->_routes[$type] : [$this->_routes[$type]];
            $this->_routes[$type] = Arr::values(Arr::unique(Arr::merge($existingRoutes, $to)));
        }

        if (Val::isNotEmpty($statement)) {
            if ($this->canCall($this->_predictor, 'addSentence')) {
                $this->_predictor->addSentence($statement);
            }
        }

        $this->updateSpellVocabulary((string)$statement);

        if ($this->_intentClassifier instanceof IntelligenceRouter) {
            $this->_intentClassifier->markDirty();
        }
    }

    public function addIntentKeywords(string $type, array $keywords, ?int $priorityBase = null): void
    {
        if (Arr::count($keywords) === 0) {
            return;
        }

        $intent = $this->_matcher->getIntent($type);
        if (!$intent) {
            $intent = new Intent($type, $type);
            $this->_matcher->registerIntent($intent);
        }

        foreach ($keywords as $keyword) {
            $keyword = Str::trim((string)$keyword);
            if (Val::isEmpty($keyword)) {
                continue;
            }

            $priority = $this->computePriority($keyword, $priorityBase ?? 12);
            $intent->addCriteria('keywords', ['word' => $keyword, 'priority' => $priority]);
        }

        $this->updateSpellVocabulary($keywords);

        if ($this->_intentClassifier instanceof IntelligenceRouter) {
            $this->_intentClassifier->markDirty();
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
        if (!$scores instanceof Arr || Arr::count($biases) === 0) {
            return $scores;
        }

        $updated = $scores->toArray();
        foreach ($biases as $label => $weight) {
            if (!Num::is($weight)) {
                continue;
            }
            $updated[$label] = ($updated[$label] ?? 0.0) + (float)$weight;
        }

        if (Arr::count($updated) > 0) {
            $updated = $this->sortScoreMap($updated);
        }

        return Arr::make($updated);
    }

    protected function sortScoreMap(array $scores): array
    {
        $pairs = [];
        foreach ($scores as $label => $score) {
            $pairs[] = [
                'label' => $label,
                'score' => $score,
            ];
        }

        $pairs = Arr::make($pairs)->sort(function ($left, $right) {
            return $right['score'] <=> $left['score'];
        })->toArray();

        $sorted = [];
        foreach ($pairs as $pair) {
            $sorted[$pair['label']] = $pair['score'];
        }

        return $sorted;
    }

    protected function computeConfidence(?Arr $scores): float
    {
        if (!$scores instanceof Arr || Arr::count($scores->toArray()) === 0) {
            return 0.0;
        }

        $values = Arr::values($scores->toArray());
        $topScore = (float)($values[0] ?? 0.0);
        $secondScore = (float)($values[1] ?? 0.0);

        if ($topScore <= 0.0) {
            return 0.0;
        }

        $denom = $topScore + $secondScore;
        return $denom > 0.0 ? ($topScore / $denom) : 0.0;
    }

    protected function normalizeInput(string $input): string
    {
        if (!$this->_spellCorrector || !$this->_spellCorrectionEnabled) {
            return $input;
        }

        $normalized = $this->_spellCorrector->normalize($input);
        if ($normalized !== $input) {
            $this->_context->set('input_original', $input);
            $this->_context->set('input_corrected', $normalized);
            Dev::do('synthetiq.input.corrected', [
                'original' => $input,
                'corrected' => $normalized,
            ]);
        }

        return $normalized;
    }

    protected function resetTurnDiagnostics(string $input): void
    {
        $this->_context->set('input_raw', $input);
        $this->_context->set('input_original', null);
        $this->_context->set('input_corrected', null);
        $this->_context->set('fallback_used', false);
        $this->_context->set('fallback_reason', null);
        $this->_context->set('memory_recall', []);
        $this->_context->set('memory_response_context', []);
        $this->_context->set('memory_selection', []);
        $this->_context->set('response_predictor', $this->responsePredictorDiagnostics());
        $this->_context->set('selected_intent_label', null);
        $this->_context->set('selected_intent_scores', []);
    }

    protected function updateSpellVocabulary($terms): void
    {
        if (!$this->_spellCorrector) {
            return;
        }

        if (Arr::is($terms)) {
            $this->_spellCorrector->addTerms($terms);
            return;
        }

        $this->_spellCorrector->addText((string)$terms);
    }

    protected function determineFallbackReason(?Intent $intent, ?Arr $scores, float $confidence, bool $allowLowConfidence): ?string
    {
        if (!$intent) {
            return 'no_intent';
        }

        if ($intent->getLabel() === 'unknown.intent') {
            return 'unknown_intent';
        }

        if (!$scores instanceof Arr || Arr::count($scores->toArray()) === 0) {
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

        if (Str::is($response) && Val::isNotEmpty($response)) {
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
            $this->_context->set('memory_response_context', $this->buildMemoryResponseContext($recall));
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

    protected function refreshResponseSelector(): void
    {
        $this->_responseSelector = new Selector($this->_predictor, [$this, 'evaluateNode']);
    }

    protected function baseResponsePredictorDiagnostics(): array
    {
        $canPredictNextWords = $this->canCall($this->_predictor, 'predictNextWords');
        $canPredictNextWord = $this->canCall($this->_predictor, 'predictNextWord');
        $canPredictBeginning = $this->canCall($this->_predictor, 'predictBeginning');

        $status = 'available';
        if ($this->_predictor === null) {
            $status = 'disabled';
        } elseif (!$canPredictNextWords && !$canPredictNextWord && !$canPredictBeginning) {
            $status = 'unavailable';
        }

        return [
            'status' => $status,
            'predictor' => is_object($this->_predictor) ? get_class($this->_predictor) : null,
            'can_predict_next_words' => $canPredictNextWords,
            'can_predict_next_word' => $canPredictNextWord,
            'can_predict_beginning' => $canPredictBeginning,
            'fallback_used' => false,
            'fallback_reason' => null,
            'error' => null,
        ];
    }

    protected function recordResponsePredictorDiagnostics(): array
    {
        $diagnostics = $this->responsePredictorDiagnostics();
        $this->_context->set('response_predictor', $diagnostics);

        if (($diagnostics['fallback_used'] ?? false) || ($diagnostics['status'] ?? 'available') !== 'available') {
            Dev::do('synthetiq.response.predictor.fallback', $diagnostics);
        }

        return $diagnostics;
    }

    protected function recordSelectedResponseIntent(string $response, array $responseIntentMap, ?MemoryRecall $memoryRecall): void
    {
        if (!Arr::hasKey($responseIntentMap, $response)) {
            return;
        }

        $label = (string)$responseIntentMap[$response];
        if (Val::isEmpty($label)) {
            return;
        }

        if ($memoryRecall instanceof MemoryRecall && !$memoryRecall->isEmpty()) {
            $this->_context->set('memory_selection', $this->buildMemorySelectionContext($memoryRecall, $response, $label));
            return;
        }

        $this->_context->set('selected_intent_label', $label);
        $this->_context->set('selected_intent_scores', [$label => 1.0]);
    }

    protected function buildResponseEnvelope(string $input, string $response): array
    {
        $intent = $this->_context->get('current_intent');
        $intentLabel = $this->_context->get('selected_intent_label');
        $scores = $this->arrayContextValue('selected_intent_scores');
        if (Val::isEmpty($scores)) {
            $scores = $this->arrayContextValue('intent_scores');
        }
        $scoredLabel = Arr::keys($scores)[0] ?? null;
        $corrected = $this->_context->get('input_corrected');
        $original = $this->_context->get('input_original');
        $memoryRecall = $this->_context->get('memory_recall');
        $memorySelection = $this->_context->get('memory_selection');
        $predictor = $this->_context->get('response_predictor');

        return [
            'response' => $response,
            'input' => [
                'raw' => $input,
                'normalized' => Str::is($corrected) && Val::isNotEmpty($corrected) ? $corrected : $input,
            ],
            'intent' => [
                'label' => Str::is($scoredLabel) ? $scoredLabel : (Str::is($intentLabel) ? $intentLabel : ($intent instanceof Intent ? $intent->getLabel() : null)),
                'confidence' => (float)($this->_context->get('intent_confidence') ?? 0.0),
                'scores' => $scores,
            ],
            'fallback' => [
                'used' => (bool)$this->_context->get('fallback_used'),
                'reason' => $this->_context->get('fallback_reason'),
            ],
            'memory' => Arr::merge(
                Arr::is($memoryRecall) ? $memoryRecall : [],
                [
                    'selection' => Arr::is($memorySelection) ? $memorySelection : [],
                ]
            ),
            'correction' => [
                'applied' => Str::is($original) && Str::is($corrected) && $original !== $corrected,
                'original' => $original,
                'corrected' => $corrected,
            ],
            'predictor' => Arr::is($predictor) ? $predictor : $this->responsePredictorDiagnostics(),
        ];
    }

    protected function arrayContextValue(string $key): array
    {
        $value = $this->_context->get($key);

        return Arr::is($value) ? $value : [];
    }

    protected function buildMemoryResponseContext(MemoryRecall $recall): array
    {
        $items = [];

        foreach ($recall->related() as $label => $entry) {
            $items[] = $this->normalizeMemoryEntry($label, $entry);
        }

        return Arr::make($items)->filter(function ($entry): bool {
            return Val::isNotEmpty($entry);
        })->toArray();
    }

    protected function buildMemorySelectionContext(MemoryRecall $recall, string $response, string $label): array
    {
        $responseContext = $this->arrayContextValue('memory_response_context');
        $matches = [];

        foreach ($responseContext as $entry) {
            if (!Arr::is($entry)) {
                continue;
            }

            $entryResponse = (string)($entry['response'] ?? '');
            $entryIntent = (string)($entry['intent_label'] ?? '');
            $matchesResponse = Val::isNotEmpty($entryResponse) && $entryResponse === $response;
            $matchesIntent = Val::isNotEmpty($entryIntent) && $entryIntent === $label;

            if ($matchesResponse || $matchesIntent) {
                $matches[] = $entry;
            }
        }

        return [
            'selected_response' => $response,
            'selected_intent' => $label,
            'related_count' => Arr::count($responseContext),
            'matched_count' => Arr::count($matches),
            'matches' => $matches,
            'intentBiases' => $recall->intentBiases(),
            'meta' => $recall->meta(),
        ];
    }

    protected function normalizeMemoryEntry($label, $entry): array
    {
        $context = Arr::is($entry) ? ($entry['context'] ?? null) : null;
        $record = [
            'label' => (string)$label,
            'similarity' => Arr::is($entry) && Arr::hasKey($entry, 'similarity') ? (float)$entry['similarity'] : null,
        ];

        if ($context instanceof Context) {
            $record['input'] = $context->get('input');
            $record['response'] = $context->get('response');
            $record['intent_label'] = $context->get('intent_label');
            $record['scope'] = $context->get('scope');
            $record['user_id'] = $context->get('user_id');
            $record['session_id'] = $context->get('session_id');
            $record['timestamp'] = $context->get('timestamp');
        } elseif (Arr::is($entry)) {
            $record = Arr::merge($record, [
                'input' => $entry['input'] ?? null,
                'response' => $entry['response'] ?? null,
                'intent_label' => $entry['intent_label'] ?? null,
                'scope' => $entry['scope'] ?? null,
                'user_id' => $entry['user_id'] ?? null,
                'session_id' => $entry['session_id'] ?? null,
                'timestamp' => $entry['timestamp'] ?? null,
            ]);
        }

        return Arr::make($record)->filter(function ($value): bool {
            return $value !== null && $value !== '';
        })->toArray();
    }

    public function evaluateNode(array $node): int
    {
        $score = 0;
        // echo $node['response'] . "\n";
        $expectedIntents = $this->_context->get('expected_intents') ?? [];
        if (!Arr::is($expectedIntents)) {
            $expectedIntents = [$expectedIntents];
        }

        // Check if the intent of the phrase is the same
        $intent = $this->_intentClassifier->classify($node['response'], $this->_context);
        if ($intent && Arr::has($expectedIntents, $intent->getLabel(), true)) {
            // echo "-- Intent match\n";
            $score += 3;
        }

        // Run in interpreter to see if it's valid
        if ( $this->_interpreter->isValid($node['response']) ) {
            // echo "-- Interpreter match\n";
            $score += 2;

            if ($this->canCall($this->_interpreter, 'tokenize') && $this->canCall($this->_interpreter, 'parse')) {
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
        if (Arr::count($sharedTokens) > 0) {
            // echo "-- Token match\n";
            $score += 3;
        }

        // Short length penalty
        $length = Str::len($node['response']);
        $penalty = 10 - Num::max(0, $length - 10);
        $score -= Num::round($penalty / 2);

        // echo "\n";

        return $score;
    }

    protected function canCall($target, string $method): bool
    {
        return is_object($target) && Func::isCallable([$target, $method]);
    }
}
