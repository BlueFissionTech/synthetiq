<?php

namespace BlueFission\SynthetIQ\Tests\Support;

use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Arr;

class FakeAnalyzer implements IAnalyzer
{
    protected $_scores;

    public function __construct(array $scores = [])
    {
        $this->_scores = $scores;
    }

    public function analyze(string $input, Context $context, array $keywords): Arr
    {
        $score = $this->_scores[$input] ?? [];

        if ($score instanceof Arr) {
            return $score;
        }

        return new Arr($score);
    }
}
