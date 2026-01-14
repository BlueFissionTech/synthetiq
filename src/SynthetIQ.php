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

    public function __construct( IInterpreter $interpreter, IAnalyzer $analyzer, ?LearningModel $learningModel = null )
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
     
        $this->_responseSelector = new Selector($this->_predictor, [$this, 'evaluateNode']);
    }

    public function processInput(string $input): string
    {
        $input = ContractionNormalizer::normalize($input);
        // Run the input through the interpreter, it will produce an output
        $this->_interpreter->run(strtolower($input));

        $intent = $this->_intentClassifier->classify($input, $this->_context);

        if ( !$intent ) {
            // If the intent is not recognized, use the last intent
            $intent = $this->_context->get('last_intent') ?? new Intent('unknown.intent', 'Unknown');
        }
        $this->_context->set('current_intent', $intent);

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

        $this->_history->addEntry($input, $response);
        $this->_context->set('last_intent', $intent);
        if ($this->_learningModel) {
            $this->_learningModel->observe($input, $response, $this->_context);
        }

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
            $this->_routes[$type] = array_values(array_unique(array_merge($this->_routes[$type], $to)));
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
            $keyword = trim((string)$keyword);
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
        $priority -= (strlen($text) / ($base / 2)) ?? $priority;

        return $priority;
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
        if (count(array_intersect(explode(' ', $node['response']), explode(' ', $this->_input))) > 0) {
            // echo "-- Token match\n";
            $score += 3;
        }

        // Short length penalty
        $length = strlen($node['response']);
        $penalty = 10 - max(0, $length - 10);
        $score -= round($penalty / 2);

        // echo "\n";

        return $score;
    }
}

