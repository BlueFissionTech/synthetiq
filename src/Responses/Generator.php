<?php

namespace BlueFission\SynthetIQ\Responses;

use BlueFission\SynthetIQ\Responses\IGenerator;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Context;
use BlueFission\HTML\Template;
use BlueFission\Collections\Collection;

class Generator implements IGenerator
{
    protected $_templates;

    public function __construct()
    {
        $this->_templates = [];
    }

    public function generate(string $input, Intent $intent, Context $context): string
    {
        $templateContent = $this->selectTemplate($intent, $context);
        $template = new Template();
        $template->contents($templateContent);
        $response = $template->set('input', $input)->render();
        
        return $response;

        // $templateContents = $this->selectTemplates($intent, $context);
        // foreach ($templateContents as $templateContent) {
        //     $template = new Template();
        //     $template->contents($templateContent);
        //     $responses[] = $template->set('input', $input)->render();
        // }

        // return $responses;
    }

    public function addTemplate($label, $statement)
    {
        $this->_templates[$label][] = $statement;
    }

    protected function generateStatement($input)
    {
        // Will attempt to generate novel statements where no templates apply
    }

    protected function selectTemplate(Intent $intent, Context $context): string
    {
        $label = $intent->getLabel();

        return (new Collection($this->_templates[$label] ?? []))->rand() ?? '';
    }

    protected function selectTemplates(Intent $intent, Context $context): array
    {
        $label = $intent->getLabel();

        return $this->_templates[$label] ?? [];
    }
}
