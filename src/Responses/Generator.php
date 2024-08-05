<?php

namespace BlueFission\SynthetIQ\Responses;

use BlueFission\SynthetIQ\Responses\IGenerator;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Context;
use BlueFission\HTML\Template;

class Generator implements IGenerator
{
    protected $_templates;

    public function __construct()
    {
        $this->_templates = new Template();
    }

    public function generate(string $input, Intent $intent, Context $context): string
    {
        $templateContent = $this->selectTemplate($intent, $context);
        $this->_templates->contents($templateContent);
        $response = $this->_templates->set('input', $input)->render();

        return $response;
    }

    protected function generateStatement($input)
    {
        
    }

    protected function selectTemplate(Intent $intent, Context $context): string
    {
        // Example template selection logic
        switch ($intent->getLabel()) {
            case 'weather':
                return "The weather today is {weather}.";
            case 'news':
                return "The latest news is {news}.";
            default:
                return "I didn't understand that.";
        }
    }
}
