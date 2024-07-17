<?php

namespace BlueFission\SynthetIQ\Responses;

use BlueFission\SynthetIQ\Responses\IGenerator;
use BlueFission\HTML\Template;

class Generator implements IResponseGenerator
{
    protected $_templates;

    public function __construct()
    {
        $this->_templates = new Template();
    }

    public function generate(string $input, string $intent, array $context): string
    {
        $templateContent = $this->selectTemplate($intent, $context);
        $response = $this->_templates->contents($templateContent)->set('input', $input)->render();

        return $response;
    }

    protected function selectTemplate(string $intent, array $context): string
    {
        // Example template selection logic
        switch ($intent) {
            case 'weather':
                return "The weather today is {weather}.";
            case 'news':
                return "The latest news is {news}.";
            default:
                return "I didn't understand that.";
        }
    }
}
