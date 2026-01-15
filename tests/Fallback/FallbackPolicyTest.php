<?php

namespace BlueFission\SynthetIQ\Tests\Fallback;

use BlueFission\Automata\Context;
use BlueFission\SynthetIQ\Fallback\FallbackResponderInterface;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Tests\Support\FakeAnalyzer;
use BlueFission\SynthetIQ\Tests\Support\FakeInterpreter;
use BlueFission\SynthetIQ\Tests\Support\MatcherResetter;
use PHPUnit\Framework\TestCase;

class FallbackPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        MatcherResetter::reset();
    }

    public function testLowConfidenceTriggersFallback(): void
    {
        $analyzer = new FakeAnalyzer([
            'hi' => [
                'greeting.intent' => 1.0,
                'status.intent' => 0.9,
            ],
        ]);
        $interpreter = new FakeInterpreter();
        $fallback = new StubFallbackResponder('fallback-response');

        $ai = new SynthetIQ($interpreter, $analyzer, null, null, $fallback);
        $ai->setConfidenceThreshold(0.7);
        $ai->addRoute('hello', 'greeting.intent', []);
        $ai->addRoute('All good.', 'status.intent', []);

        $response = $ai->processInput('hi');

        $this->assertSame('fallback-response', $response);
        $this->assertCount(1, $fallback->calls);
    }
}

class StubFallbackResponder implements FallbackResponderInterface
{
    public array $calls = [];
    private string $response;

    public function __construct(string $response)
    {
        $this->response = $response;
    }

    public function respond(string $input, Context $context, array $meta = []): ?string
    {
        $this->calls[] = [
            'input' => $input,
            'meta' => $meta,
        ];

        return $this->response;
    }
}
