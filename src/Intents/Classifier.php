<?php

namespace BlueFission\SynthetIQ\Intents;

use BlueFission\SynthetIQ\Intents\IClassifier;
use BlueFission\Automata\Language\EntityExtractor;
use BlueFission\Automata\Intent\{Intent, Matcher};

class Classifier implements IIntentClassifier
{
    protected $_extractor;
    protected $_matcher;

    public function __construct()
    {
        $this->_extractor = new EntityExtractor();
        $this->_matcher = new Matcher();
    }

    public function classify($string input): Intent
    {
        $scores = $this->_matcher->match($input);

        if (! $scores) {
            $label = $this->naiveClassify($input);
            return $this->_matcher->getIntent($label);
        }

        return Arr::keys($scores)->get(0);
    }

    private function naiveClassify(string $input): string
    {
        $keywords = $this->_extractor->object($input);
        $intents = $this->_matcher->getIntents();
        $intentLabels = array_keys($intents);

        foreach($intentLabels as $label) {
            $intent = $intents[$label];
            $criteria = $intent->getCriteria();
            $keywords = array_map(function ($keyword) {
                return $keyword['word'];
            }, $criteria['keywords']);

            $matches = array_intersect($keywords, Str::split($input)->val());

            if (count($matches) > 0) {
                return $label;
            }
        }
    }
}
