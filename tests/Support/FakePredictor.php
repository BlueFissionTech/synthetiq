<?php

namespace BlueFission\SynthetIQ\Tests\Support;

class FakePredictor
{
    protected $_next_words;
    protected $_beginning;

    public function __construct(array $next_words = [], ?string $beginning = null)
    {
        $this->_next_words = $next_words;
        $this->_beginning = $beginning;
    }

    public function addSentence($sentence): void
    {
    }

    public function predictNextWords($previousTwoWords): array
    {
        return $this->_next_words[$previousTwoWords] ?? [];
    }

    public function predictBeginning(): ?string
    {
        return $this->_beginning;
    }
}
