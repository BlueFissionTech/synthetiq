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

// remove
use BlueFission\Automata\Collections\OrganizedCollection;

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

    public function __construct( IInterpreter $interpreter, IAnalyzer $analyzer )
    {
        $this->_context = new Context();
        $this->_history = new ConversationHistory();
        $this->_intentClassifier = new Classifier( $analyzer );
        $this->_responseGenerator = new Generator();
        $this->_matcher = new Matcher($analyzer);
        $this->_predictor = new TrigramMarkovPredictor();
        $this->_routes = [];
        $this->_interpreter = $interpreter;
     
        $this->_responseSelector = new Selector($this->_predictor, [$this, 'evaluateNode']);
    }

    public function processInput(string $input): string
    {
        // Run the input through the interpreter, it will produce an output
        $this->_interpreter->run(strtolower($input));

        // This is largely useless for right now. More useful will be to get the interpreter output and add it to the context
        $tree = $this->_interpreter->getTree();

        $intent = $this->_intentClassifier->classify($input, $this->_context);

        if ( !$intent ) {
            // If the intent is not recognized, use the last intent
            $intent = $this->_context->get('last_intent') ?? new Intent('unknown.intent', 'Unknown');
        }
        $this->_context->set('current_intent', $intent);

        $responseTypes = $this->_routes[$intent->getLabel()] ?? [];

        foreach ($responseTypes as $responseType) {
            $intent = $this->_matcher->getIntent($responseType);
            $value = $this->_responseGenerator->generate($input, $intent, $this->_context);
            $responses[$value] = $value;
            // var_dump($responseType, $intent, $value);
        }

        $this->_context->set('expected_intents', $responseTypes);

        $this->_input = $input;
        $response = $this->_responseSelector->select($input, $responses, $this->_context);
        $this->_input = '';

        $this->_history->addEntry($input, $response);
        $this->_context->set('last_intent', $intent);

        return $response;
    }

    public function addRoute($statement, $type, $to = []) {
        $intent = $this->_matcher->getIntent($type);
        if ( !$intent ) {
            $intent = new Intent($type, $type);
            $this->_matcher->registerIntent($intent);
        }

        $priority = 10;
        $priority -= (strlen($statement) / ($priority/2)) ?? $priority;
        $intent->addCriteria('keywords', ['word' => $statement, 'priority' => $priority]);
        $this->_responseGenerator->addTemplate($type, $statement);
        
        // foreach ($to as $category) {
        //     $this->_responseGenerator->addTemplate($category, $statement);
        // }

        $this->_routes[$type] = $to;

        $this->_predictor->addSentence($statement);

        echo ".";
    }

    public function evaluateNode(array $node): int
    {
        $score = 0;
        // echo $node['response'] . "\n";
        // Retrieve current and last intents for matching
        $currentIntent = $this->_context->get('current_intent');
        $lastIntent = $this->_context->get('last_intent');

        // Check if the intent of the phrase is the same
        $intent = $this->_intentClassifier->classify($node['response'], $this->_context);
        if (in_array($intent->getLabel(), $this->_context->get('expected_intents'))) {
            // echo "-- Intent match\n";
            $score += 3;
        }

        // Run in interpreter to see if it's valid
        if ( $this->_interpreter->isValid($node['response']) ) {
            // echo "-- Interpreter match\n";
            $score += 2;

            $tokens = $this->_interpreter->tokenize($node['response']);
            $output = $this->_interpreter->parse($tokens);

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

class TrigramMarkovPredictor {
    protected $states;
    protected $beginnings;

    public function __construct() {
        $this->states = new OrganizedCollection();
        $this->states->setMax(10000); // Set max states to keep
        $this->states->setDecay(true, 0.001); // Enable decay with a specific rate
        $this->beginnings = new OrganizedCollection();
        $this->beginnings->setMax(1000); // Manage the size of beginnings similarly
    }

    public function addSentence($sentence) {
        $this->states->setSort(false);
        $words = $this->tokenize($sentence);
        if (count($words) < 2) {
            return; // Skip sentences that are too short to form a trigram
        }

        // Add the first word sequence to beginnings for potential initial states
        $key = $words[0] . ' ' . $words[1];
        $this->beginnings->add($key);

        for ($i = 2; $i < count($words); $i++) {
            $firstWord = $words[$i - 2];
            $secondWord = $words[$i - 1];
            $trigramKey = $firstWord . ' ' . $secondWord;
            $thirdWord = $words[$i];

            // Add the first word sequence to beginnings for potential initial states
            if (!$this->states->has($firstWord)) {
                $this->states->add([], $firstWord);
            }
            if (!$this->states->has($trigramKey)) {
                $this->states->add([], $trigramKey);
            }

            // Add the next word to the current state
            $currentData = $this->states->get($firstWord);
            if (!isset($currentData[$secondWord])) {
                $currentData[$secondWord] = 0;
            }
            $currentData[$secondWord]++;
            $this->states->add($currentData, $firstWord);

            $currentData = $this->states->get($trigramKey);
            if (!isset($currentData[$thirdWord])) {
                $currentData[$thirdWord] = 0;
            }
            $currentData[$thirdWord]++;
            $this->states->add($currentData, $trigramKey);
        }
        $this->beginnings->setSort(true);
        
        $this->states->setSort(true);
    }

    public function tokenize($sentence) {
        // Simple tokenizer (consider improving or using a library for better tokenization)
        return preg_split('/\s+/', strtolower($sentence));
    }

    public function predictNextWord($previousTwoWords) {
        if ($this->states->has($previousTwoWords)) {
            $nextWords = $this->states->get($previousTwoWords);
            $total = array_sum($nextWords);
            $rand = mt_rand(0, $total - 1);

            foreach ($nextWords as $word => $count) {
                if (($rand -= $count) < 0) {
                    return $word;
                }
            }
        }
        return null; // No next word found if no suitable transition exists
    }

    public function predictBeginning() {
        if ($this->beginnings->count() > 0) {
            $beginning = $this->beginnings->rand();
            return $beginning;
        }
        return null; // No beginning found if none have been added
    }

    public function predictSentence() {
        $beginning = $this->predictBeginning();
        if ($beginning === null) {
            return null; // No sentence can be predicted if no beginning is found
        }

        $words = $this->tokenize($beginning);
        $sentence = $beginning;

        while (true) {
            $nextWord = $this->predictNextWord($words[count($words) - 2] . ' ' . $words[count($words) - 1]);
            if ($nextWord === null) {
                break; // Stop if no next word can be predicted
            }
            $sentence .= ' ' . $nextWord;
            $words[] = $nextWord;
        }

        return $sentence;
    }

    public function predictNextWords($previousTwoWords) {
        $previousTwoWords = strtolower($previousTwoWords);

        if ($this->states->has($previousTwoWords)) {
            $nextWords = $this->states->get($previousTwoWords);

            uasort($nextWords, function($a, $b) {
                return $b - $a;
            });

            $sortedWords = array_keys($nextWords);

            return $sortedWords;
        }

        return []; // No next word found if no suitable transition exists
    }
}
