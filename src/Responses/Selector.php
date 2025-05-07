<?php

namespace BlueFission\SynthetIQ\Responses;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\DepthFirstMethod;
use BlueFission\Automata\DecisionTree\Node;

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

        $responses = array_keys($responses);

        $this->buildDecisionTree($input, $responses);

        $selectedNode = $this->_decisionTree->decide(new DepthFirstMethod());

        return $selectedNode ? $selectedNode['response'] : $responses[array_rand($responses)];
    }

    protected function buildDecisionTree($input, $samples): void
    {
        // Example decision tree building logic
        $defaultResponse = $samples[array_rand($samples)];
        $rootNode = new Node(['response' => $defaultResponse], $this->_evalutation);

        // Get the most likely first words as nodes from the default responses
        $firstWords = array_map(function($response) {
            return explode(' ', $response)[0];
        }, $samples);

        if (empty($firstWords)) {
            $firstWords[] = $this->_predictor->predictBeginning();
        }

        if (empty($firstWords)) 
            return;

        $count = count($firstWords) <= $this->_maxChildren ? count($firstWords) : $this->_maxChildren;

        for($i = 0; $i < $count; $i++) {
            $token = $firstWords[$i];
            $value = $token;
            $newNode = new Node(['response' => $input], $this->_evalutation);
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
        $tokens = $this->_predictor->predictNextWords($input);

        if (empty($tokens) && $this->_depth < 8 && $this->_useSingleTokenKey > 0) {
            $this->_useSingleTokenKey--;
            $lastWord = explode(' ', $input);
            $tokens = $this->_predictor->predictNextWords(end($lastWord));
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

            if ($this->_depth <= $this->_maxDepth && !preg_match('/[.!?]$/', trim($value)) && strlen($value) < 100) {
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
}
