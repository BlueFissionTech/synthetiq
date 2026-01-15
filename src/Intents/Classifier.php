<?php

namespace BlueFission\SynthetIQ\Intents;

use BlueFission\SynthetIQ\Intents\IClassifier;
use BlueFission\Automata\Language\EntityExtractor;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Intent\{Intent, Matcher};
use BlueFission\Automata\Context;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Collections\Collection;

class Classifier implements IClassifier
{
    protected $_extractor;
    protected $_matcher;

    public function __construct( IAnalyzer $analyzer )
    {
        $this->_extractor = new EntityExtractor();
        $this->_matcher = new Matcher($analyzer);
    }

    public function score(string $input, Context $context): ?Arr
    {
        try {
            $scores = $this->_matcher->match($input, $context);
        } catch (\Throwable $e) {
            $scores = null;
        }

        return $scores instanceof Arr ? $scores : null;
    }

    public function classify(string $input, Context $context): ?Intent
    {
        $scores = $this->score($input, $context);

        return $this->classifyFromScores($input, $context, $scores);
    }

    public function classifyFromScores(string $input, Context $context, ?Arr $scores): ?Intent
    {
        $label = $this->labelFromScores($input, $scores);
        if (!$label) {
            return null;
        }

        return $this->_matcher->getIntent($label);
    }

    public function labelFromScores(string $input, ?Arr $scores): ?string
    {
        if (!$scores instanceof Arr || $scores->count() === 0) {
            return $this->naiveClassify($input);
        }

        $label = $scores->keys()->get(0);
        if (!$label) {
            return $this->naiveClassify($input);
        }

        return $label;
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

            $keywords = (new Collection($criteriaKeywords))
                ->map(function ($keyword) {
                    return $keyword['word'] ?? null;
                })
                ->filter(function ($keyword) {
                    return $keyword !== null && $keyword !== '';
                })
                ->toArray();

            $matches = Arr::intersect($keywords, Str::split($input));

            if (!empty($matches)) {
                return $label;
            }
        }

        return null;
    }
}
