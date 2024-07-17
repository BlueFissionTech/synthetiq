<?php

namespace BlueFission\SynthetIQ;

use BlueFission\Arr;

class ConversationHistory
{
    protected $_history;

    public function __construct()
    {
        $this->_history = new Arr();
    }

    public function addEntry($input, $response): void
    {
        $this->_history->push(['input' => $input, 'response' => $response]);
    }

    public function getHistory(): Arr
    {
        return $this->_history;
    }
}
