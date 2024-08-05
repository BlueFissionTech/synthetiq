<?php

namespace BlueFission\SynthetIQ;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Language\IInterpreter;
use BlueFission\SynthetIQ\ConversationHistory;
use BlueFission\SynthetIQ\Intents\Classifier;
use BlueFission\SynthetIQ\Responses\Generator;
use BlueFission\SynthetIQ\Responses\Selector;
use BlueFission\Automata\Analysis\IAnalyzer;

class SynthetIQ
{
    protected $_context;
    protected $_history;
    protected $_interpreter;
    protected $_intentClassifier;
    protected $_responseGenerator;
    protected $_responseSelector;

    public function __construct( IInterpreter $interpreter, IAnalyzer $analyzer )
    {
        $this->_context = new Context();
        $this->_history = new ConversationHistory();
        $this->_intentClassifier = new Classifier( $analyzer );
        $this->_responseGenerator = new Generator();
        $this->_responseSelector = new Selector();
        
        $this->_interpreter = $interpreter;
    }

    public function processInput(string $input): string
    {
        $this->_interpreter->run($input);
        $tree = $this->_interpreter->getTree();

        // die(var_dump($tree));

        $intent = $this->_intentClassifier->classify($input, $this->_context);

        if ( !$intent ) {
            $intent = $this->_context->get('last_intent') ?? new Intent('unknown.intent', 'Unknown');
        }

        $responses = [$this->_responseGenerator->generate($input, $intent, $this->_context)];
        $response = $this->_responseSelector->select($responses, $this->_context);

        $this->_history->addEntry($input, $response);
        $this->_context->set('last_intent', $intent);

        return $response;
    }
}
