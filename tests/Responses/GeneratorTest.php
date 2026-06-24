<?php

namespace BlueFission\SynthetIQ\Tests\Responses;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\SynthetIQ\Responses\Generator;
use PHPUnit\Framework\TestCase;

class GeneratorTest extends TestCase
{
    public function testScriptedBlocksRemainLiteralWhenDisabled(): void
    {
        $generator = new Generator();
        $context = new Context();
        $intent = new Intent('reply.intent', 'Reply');
        $generator->addTemplate('reply.intent', 'Hello {= input}');

        $response = $generator->generate('Ada', $intent, $context);

        $this->assertSame('Hello {= input}', $response);
        $this->assertFalse($generator->scriptedTemplateDiagnostics()['enabled']);
    }

    public function testScriptedBlocksResolveVariablesAndTransforms(): void
    {
        $generator = new Generator();
        $generator->enableScriptedTemplates(true);
        $context = new Context();
        $context->set('user', ['name' => 'ada']);
        $intent = new Intent('reply.intent', 'Reply');
        $generator->addTemplate(
            'reply.intent',
            'Hello {= capitalize(context.user.name) }, input {= upper(input) }, intent {= intent}'
        );

        $response = $generator->generate('status', $intent, $context);
        $diagnostics = $generator->scriptedTemplateDiagnostics();

        $this->assertSame('Hello Ada, input STATUS, intent reply.intent', $response);
        $this->assertTrue($diagnostics['enabled']);
        $this->assertSame([], $diagnostics['errors']);
        $this->assertSame(3, Arr::count($diagnostics['blocks']));
    }

    public function testMissingVariablesResolveEmptyAndAreReported(): void
    {
        $generator = new Generator();
        $generator->enableScriptedTemplates(true);
        $intent = new Intent('reply.intent', 'Reply');
        $generator->addTemplate('reply.intent', 'Missing:{= context.user.name}');

        $response = $generator->generate('status', $intent, new Context());
        $diagnostics = $generator->scriptedTemplateDiagnostics();

        $this->assertSame('Missing:', $response);
        $this->assertSame('missing', $diagnostics['errors'][0]['reason']);
        $this->assertSame('context.user.name', $diagnostics['errors'][0]['expression']);
    }

    public function testInvalidScriptsResolveEmptyAndAreReported(): void
    {
        $generator = new Generator();
        $generator->enableScriptedTemplates(true);
        $intent = new Intent('reply.intent', 'Reply');
        $generator->addTemplate('reply.intent', 'Invalid:{= drop table}');

        $response = $generator->generate('status', $intent, new Context());
        $diagnostics = $generator->scriptedTemplateDiagnostics();

        $this->assertSame('Invalid:', $response);
        $this->assertSame('invalid', $diagnostics['errors'][0]['reason']);
    }
}
