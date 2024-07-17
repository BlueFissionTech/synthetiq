<?php

namespace BlueFission\SynthetIQ;

use BlueFission\Automata\Context;
use BlueFission\Automata\Language\IInterpreter;
use BlueFission\SynthetIQ\ConversationHistory;
use BlueFission\SynthetIQ\Intents\Classifier;
use BlueFission\SynthetIQ\Responses\Generator;
use BlueFission\SynthetIQ\Responses\Selector;


class SynthetIQ
{
    protected $_context;
    protected $_history;
    protected $_interpreter;
    protected $_intentClassifier;
    protected $_responseGenerator;
    protected $_responseSelector;

    public function __construct( IInterpreter $interpreter )
    {
        $this->_context = new Context();
        $this->_history = new ConversationHistory();
        $this->_intentClassifier = new Classifier();
        $this->_responseGenerator = new Generator();
        $this->_responseSelector = new Selector();
        
        $this->_interpreter = $interpreter;
    }

    public function processInput(string $input): string
    {
        $this->_interpreter->run($input);
        $intent = $this->_intentClassifier->classify($input);
        $contextData = $this->_context->all();
        $responses = [$this->_responseGenerator->generate($input, $intent, $contextData)];
        $response = $this->_responseSelector->select($responses, $contextData);

        $this->_history->addEntry($input, $response);
        $this->_context->set('last_intent', $intent);

        return $response;
    }
}
