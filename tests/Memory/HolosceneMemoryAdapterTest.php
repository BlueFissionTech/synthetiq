<?php

namespace BlueFission\SynthetIQ\Tests\Memory;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Memory\Abs2Memory;
use BlueFission\SynthetIQ\Memory\HolosceneMemoryAdapter;
use PHPUnit\Framework\TestCase;

class HolosceneMemoryAdapterTest extends TestCase
{
    public function testRecallBuildsIntentBiases(): void
    {
        $memory = new Abs2Memory();
        $adapter = new HolosceneMemoryAdapter($memory, null, null, [
            'similarity_threshold' => 0.1,
            'max_related' => 5,
            'bias_weight' => 1.0,
            'default_scope' => 'user',
        ]);

        $context = new Context();
        $context->set('current_intent', new Intent('greeting.intent', 'Greeting'));
        $adapter->recordExchange('hello there', 'Hi!', $context, ['scope' => 'user']);

        $context->set('current_intent', new Intent('status.intent', 'Status'));
        $adapter->recordExchange('status report', 'All good.', $context, ['scope' => 'user']);

        $recall = $adapter->recall('hello', new Context(), ['scope' => 'user']);
        $biases = $recall->intentBiases();

        $this->assertArrayHasKey('greeting.intent', $biases);
    }
}
