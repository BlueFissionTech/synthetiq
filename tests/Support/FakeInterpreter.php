<?php

namespace BlueFission\SynthetIQ\Tests\Support;

use BlueFission\Automata\Language\IInterpreter;

class FakeInterpreter implements IInterpreter
{
    protected $_valid;
    protected $_last_run;

    public function __construct(bool $valid = true)
    {
        $this->_valid = $valid;
    }

    public function load($file)
    {
        return null;
    }

    public function run($code)
    {
        $this->_last_run = $code;
        return null;
    }

    public function isValid($code): bool
    {
        return $this->_valid;
    }

    public function getTree(): array
    {
        return [];
    }

    public function tokenize(string $code): array
    {
        $tokens = preg_split('/\s+/', trim($code));
        return $tokens ?: [];
    }

    public function parse(array $tokens): array
    {
        return $tokens;
    }
}
