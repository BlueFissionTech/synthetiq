<?php

namespace BlueFission\SynthetIQ\Responses;

use BlueFission\SynthetIQ\Responses\IGenerator;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Context;
use BlueFission\HTML\Template;
use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\Str;
use BlueFission\Val;

class Generator implements IGenerator
{
    protected const SCRIPTED_BLOCK_TOKEN = "\x1Fsynthetiq_script_block\x1F";

    protected $_templates;
    protected bool $_scriptedTemplatesEnabled = false;
    protected ScriptedTemplateRenderer $_scriptedTemplateRenderer;

    public function __construct()
    {
        $this->_templates = [];
        $this->_scriptedTemplateRenderer = new ScriptedTemplateRenderer();
    }

    public function generate(string $input, Intent $intent, Context $context): string
    {
        $templateContent = $this->selectTemplate($intent, $context);
        $contextData = $this->contextData($context);
        if ($this->_scriptedTemplatesEnabled) {
            $templateContent = $this->_scriptedTemplateRenderer->render($templateContent, [
                'input' => $input,
                'intent' => $intent->getLabel(),
                'context' => $contextData,
            ]);
        } else {
            $templateContent = $this->protectScriptedBlocks($templateContent);
        }

        $template = new Template();
        $template->contents($templateContent);
        $template->set('input', $input);
        $template->set('intent', $intent->getLabel());
        if (Val::isNotEmpty($contextData)) {
            $template->set('context', $contextData);
        }
        $response = $template->render();
        if (!$this->_scriptedTemplatesEnabled) {
            $response = $this->restoreScriptedBlocks($response);
        }
        
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

    public function enableScriptedTemplates(bool $enabled): void
    {
        $this->_scriptedTemplatesEnabled = $enabled;
    }

    public function setScriptedTemplateRenderer(?ScriptedTemplateRenderer $renderer): void
    {
        $this->_scriptedTemplateRenderer = $renderer ?? new ScriptedTemplateRenderer();
    }

    public function scriptedTemplateDiagnostics(): array
    {
        if (!$this->_scriptedTemplatesEnabled) {
            return [
                'enabled' => false,
                'blocks' => [],
                'errors' => [],
            ];
        }

        return $this->_scriptedTemplateRenderer->lastDiagnostics();
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

    protected function contextData(Context $context): array
    {
        return (new Collection($context->all()))
            ->filter(static function ($value) {
                if (Arr::is($value)) {
                    return Val::isNotEmpty($value);
                }

                return $value !== null && $value !== '';
            })
            ->toArray();
    }

    protected function protectScriptedBlocks(string $templateContent): string
    {
        return Str::replace($templateContent, '{=', self::SCRIPTED_BLOCK_TOKEN);
    }

    protected function restoreScriptedBlocks(string $response): string
    {
        return Str::replace($response, self::SCRIPTED_BLOCK_TOKEN, '{=');
    }
}
