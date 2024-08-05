<?php

namespace BlueFission\SynthetIQ\Intents;

use BlueFission\SynthetIQ\Intents\IClassifier;
use BlueFission\Automata\Language\EntityExtractor;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Intent\{Intent, Matcher};
use BlueFission\Automata\Context;
use BlueFission\Arr;

class Classifier implements IClassifier
{
    protected $_extractor;
    protected $_matcher;

    public function __construct( IAnalyzer $analyzer )
    {
        $this->_extractor = new EntityExtractor();
        $this->_matcher = new Matcher($analyzer);
    }

    public function classify(string $input, Context $context): ?Intent
    {
        $scores = $this->_matcher->match($input, $context);

        if (! $scores) {
            $label = $this->naiveClassify($input);
            return $this->_matcher->getIntent($label);
        }

        return $this->_matcher->getIntent( $scores->keys()->get(0) );
    }

    private function naiveClassify(string $input): string
    {
        $keywords = $this->_extractor->object($input);
        $intents = $this->_matcher->getIntents();
        $intentLabels = Arr::keys($intents);

        foreach($intentLabels as $label) {
            $intent = $intents[$label];
            $criteria = $intent->getCriteria();
            $keywords = Arr::map(function ($keyword) {
                return $keyword['word'];
            }, $criteria['keywords']);

            $matches = Arr::intersect($keywords, Str::split($input)->val());

            if (Arr::size($matches) > 0) {
                return $label;
            }
        }
    }
}
