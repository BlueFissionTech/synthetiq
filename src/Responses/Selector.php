<?php

namespace BlueFission\SynthetIQ\Responses;

use BlueFission\Arr;
use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\DepthFirstMethod;
use BlueFission\Automata\DecisionTree\Node;

class Selector
{
    protected $_decisionTree;

    public function __construct()
    {
        $this->_decisionTree = new DecisionTree();
        $this->buildDecisionTree();
    }

    public function select(array $responses, array $context): string
    {
        $method = new DepthFirstMethod();
        $selectedNode = $method->traverse($this->_decisionTree->getRoot());

        return $selectedNode ? $selectedNode->getValue()['response'] : $responses[array_rand($responses)];
    }

    protected function buildDecisionTree(): void
    {
        // Example decision tree building logic
        $rootNode = new Node(['response' => 'Default response'], [$this, 'evaluateNode']);
        $weatherNode = new Node(['response' => 'The weather today is sunny.'], [$this, 'evaluateNode']);
        $newsNode = new Node(['response' => 'The latest news is...'], [$this, 'evaluateNode']);

        $rootNode->addChild($weatherNode);
        $rootNode->addChild($newsNode);

        $this->_decisionTree->setRoot($rootNode);
    }

    public function evaluateNode(array $value): int
    {
        // Example evaluation function
        return rand(0, 100); // A placeholder evaluation function that returns a random score
    }
}
