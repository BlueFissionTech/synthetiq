<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Policy;

use BlueFission\Automata\Context;
use BlueFission\SynthetIQ\Audit\AuditTrail;
use BlueFission\SynthetIQ\Policy\NullPolicyFilter;
use BlueFission\SynthetIQ\Policy\PolicyDecision;
use BlueFission\SynthetIQ\Policy\PolicyFilterInterface;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Tests\Support\FakeAnalyzer;
use BlueFission\SynthetIQ\Tests\Support\FakeInterpreter;
use BlueFission\SynthetIQ\Tests\Support\MatcherResetter;
use PHPUnit\Framework\TestCase;

final class PolicyFilterTest extends TestCase
{
    protected function setUp(): void
    {
        MatcherResetter::reset();
    }

    public function testNullPolicyAllowsInputAndOutput(): void
    {
        $filter = new NullPolicyFilter();
        $context = new Context();

        $this->assertTrue($filter->inspectInput('hello', $context)->allowed());
        $this->assertTrue($filter->inspectOutput('Hello there', $context)->allowed());
    }

    public function testInputPolicyCanDenyTurnAndAuditDecision(): void
    {
        $ai = new SynthetIQ(new FakeInterpreter(), new FakeAnalyzer([]));
        $ai->addPolicyFilter(new class implements PolicyFilterInterface {
            public function inspectInput(string $input, Context $context, array $meta = []): PolicyDecision
            {
                return PolicyDecision::deny('blocked_input', 'Input denied.');
            }

            public function inspectOutput(string $output, Context $context, array $meta = []): PolicyDecision
            {
                return PolicyDecision::allow('unused');
            }
        });

        $envelope = $ai->processInputEnvelope('secret');

        $this->assertSame('Input denied.', $envelope['response']);
        $this->assertTrue($envelope['policy']['denied']);
        $this->assertSame('blocked_input', $envelope['policy']['reason']);
        $events = [];
        foreach ($envelope['audit'] as $record) {
            $events[] = $record['event'];
        }
        $this->assertContains('policy.input.denied', $events);
    }

    public function testOutputPolicyCanReplaceResponse(): void
    {
        $ai = new SynthetIQ(new FakeInterpreter(), new FakeAnalyzer([
            'hello' => ['greeting.intent' => 1],
        ]));
        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('private response', 'reply.intent', []);
        $ai->addPolicyFilter(new class implements PolicyFilterInterface {
            public function inspectInput(string $input, Context $context, array $meta = []): PolicyDecision
            {
                return PolicyDecision::allow('input_allowed');
            }

            public function inspectOutput(string $output, Context $context, array $meta = []): PolicyDecision
            {
                return PolicyDecision::deny('blocked_output', 'Response withheld.');
            }
        });

        $envelope = $ai->processInputEnvelope('hello');

        $this->assertSame('Response withheld.', $envelope['response']);
        $this->assertTrue($envelope['policy']['denied']);
        $this->assertSame('blocked_output', $envelope['policy']['reason']);
    }

    public function testAuditTrailAppliesRedactor(): void
    {
        $audit = new AuditTrail();
        $audit->setRedactor(static function (mixed $value): mixed {
            return $value === 'secret' ? '[redacted]' : $value;
        });

        $audit->record('sample', ['token' => 'secret']);

        $this->assertSame('[redacted]', $audit->records()[0]['payload']['token']);
    }
}
