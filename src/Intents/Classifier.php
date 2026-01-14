<?php

namespace BlueFission\SynthetIQ\Intents;

use BlueFission\SynthetIQ\Intents\IClassifier;
use BlueFission\Automata\Language\EntityExtractor;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Intent\{Intent, Matcher};
use BlueFission\Automata\Context;
use BlueFission\Arr;
use BlueFission\Str;

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
        try {
            $scores = $this->_matcher->match($input, $context);
        } catch (\Throwable $e) {
            $scores = null;
        }

        if (!$scores instanceof Arr || $scores->count() === 0) {
            $label = $this->naiveClassify($input);
            if (!$label) {
                return null;
            }

            return $this->_matcher->getIntent($label);
        }

        $label = $scores->keys()->get(0);
        if (!$label) {
            return null;
        }

        return $this->_matcher->getIntent($label);
    }

    private function naiveClassify(string $input): ?string
    {
        $intents = $this->_matcher->getIntents();
        $intentLabels = Arr::keys($intents);

        foreach($intentLabels as $label) {
            $intent = $intents[$label];
            $criteria = $intent->getCriteria();
            $criteriaKeywords = $criteria['keywords'] ?? [];
            if (empty($criteriaKeywords)) {
                continue;
            }

            $keywords = array_map(function ($keyword) {
                return $keyword['word'] ?? null;
            }, $criteriaKeywords);
            $keywords = array_filter($keywords);

            $matches = array_intersect($keywords, Str::split($input));

            if (!empty($matches)) {
                return $label;
            }
        }

        return null;
    }
}
