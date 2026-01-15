<?php

namespace BlueFission\SynthetIQ\Responses;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\DepthFirstMethod;
use BlueFission\Automata\DecisionTree\Node;
use BlueFission\Collections\Collection;
use BlueFission\Str;

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

        if (empty($responses)) {
            return '';
        }

        $responses = Arr::keys($responses);
        $this->_depth = 0;
        $this->_useSingleTokenKey = $this->_maxSingleTokenKeyPatterns;

        $this->buildDecisionTree($input, $responses);

        $selectedNode = $this->_decisionTree->decide(new DepthFirstMethod());

        // Only return a response that was explicitly provided as a candidate.
        if ($selectedNode && in_array($selectedNode['response'], $responses, true)) {
            return $selectedNode['response'];
        }

        return (new Collection($responses))->rand();
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
            if (method_exists($this->_predictor, 'predictBeginning')) {
                $firstWords[] = $this->_predictor->predictBeginning();
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
        $tokens = [];
        if (method_exists($this->_predictor, 'predictNextWords')) {
            $tokens = $this->_predictor->predictNextWords($input);
        } elseif (method_exists($this->_predictor, 'predictNextWord')) {
            for ($i = 0; $i < $this->_maxChildren; $i++) {
                $next = $this->_predictor->predictNextWord($input);
                if ($next) {
                    $tokens[] = $next;
                }
            }
            $tokens = array_values(Arr::unique($tokens));
        }

        if (empty($tokens) && $this->_depth < 8 && $this->_useSingleTokenKey > 0) {
            $this->_useSingleTokenKey--;
            $lastWord = (new Collection(Str::split($input, ' ')))->last();
            $lastWord = (string)($lastWord ?: '');
            if (method_exists($this->_predictor, 'predictNextWords')) {
                $tokens = $this->_predictor->predictNextWords($lastWord);
            } elseif (method_exists($this->_predictor, 'predictNextWord')) {
                $next = $this->_predictor->predictNextWord($lastWord);
                if ($next) {
                    $tokens = [$next];
                }
            }
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
}
