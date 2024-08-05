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

    public function __construct()
    {
        $this->_decisionTree = new DecisionTree();
        $this->buildDecisionTree();
    }

    public function select(array $responses, Context $context): string
    {
        $method = new DepthFirstMethod();
        $selectedNode = $method->traverse($this->_decisionTree->getRoot());

        return $selectedNode ? $selectedNode['response'] : $responses[array_rand($responses)];
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
        // return an integer value between 0 and 10
        return random_int(0, 10);
    }
}
