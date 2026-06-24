<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\State;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\SynthetIQ\State\ConversationState;
use PHPUnit\Framework\TestCase;

final class ConversationStateTest extends TestCase
{
    public function testDefaultsCanBeUpdatedSerializedAndReset(): void
    {
        $state = new ConversationState();

        $this->assertSame('idle', $state->toArray()['task']['state']);

        $state
            ->setPersona('Guide', 'assistant', ['calm', 'bounded', ''])
            ->setTone('warm')
            ->setMood('steady')
            ->setTaskState('collecting')
            ->setSlot('goal', 'demo')
            ->setSession('session-1', 'user-1', 'scope-1')
            ->setMetadata('source', 'test');

        $payload = $state->toArray();

        $this->assertSame('Guide', $payload['persona']['name']);
        $this->assertSame(['calm', 'bounded'], $payload['persona']['traits']);
        $this->assertSame('demo', $state->slot('goal'));
        $this->assertSame('fallback', $state->slot('missing', 'fallback'));
        $this->assertSame('scope-1', $payload['session']['scope']);

        $state->reset();

        $this->assertSame('idle', $state->toArray()['task']['state']);
        $this->assertSame([], $state->toArray()['task']['slots']);
    }

    public function testRestoresArrayStateAndAppliesToContext(): void
    {
        $state = ConversationState::fromArray(require dirname(__DIR__, 2) . '/sample_configs/conversation_state.php');
        $context = new Context();

        $state->applyToContext($context);

        $this->assertSame('SynthetIQ Guide', $context->get('persona_name'));
        $this->assertSame('calm', $context->get('tone'));
        $this->assertSame('intake', $context->get('task_state'));
        $this->assertSame('sample-conversation', $context->get('memory_scope'));
        $this->assertSame('route a visitor through a known conversation path', $context->get('task_slots')['goal']);
    }

    public function testCapturesTurnSummary(): void
    {
        $state = new ConversationState();
        $state->captureTurn(new Intent('status.intent', 'Status'), 'All good.');

        $turn = $state->toArray()['turn'];

        $this->assertSame(1, $turn['count']);
        $this->assertSame('status.intent', $turn['last_intent']);
        $this->assertSame('All good.', $turn['last_response']);
    }
}
