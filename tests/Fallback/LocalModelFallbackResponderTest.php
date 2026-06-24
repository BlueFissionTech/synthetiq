<?php

namespace BlueFission\SynthetIQ\Tests\Fallback;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\SynthetIQ\Fallback\FallbackProviderInterface;
use BlueFission\SynthetIQ\Fallback\LocalModelFallbackResponder;
use BlueFission\SynthetIQ\Fallback\TrainingCandidateStore;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Tests\Support\FakeAnalyzer;
use BlueFission\SynthetIQ\Tests\Support\FakeInterpreter;
use BlueFission\SynthetIQ\Tests\Support\MatcherResetter;
use PHPUnit\Framework\TestCase;

class LocalModelFallbackResponderTest extends TestCase
{
    protected function setUp(): void
    {
        MatcherResetter::reset();
    }

    public function testResponderIsDisabledByDefault(): void
    {
        $provider = new FakeFallbackProvider('model answer');
        $store = new TrainingCandidateStore();
        $responder = new LocalModelFallbackResponder($provider, $store);

        $response = $responder->respond('hello', new Context(), ['reason' => 'unknown_intent']);

        $this->assertNull($response);
        $this->assertSame([], $store->all());
        $this->assertSame([], $provider->prompts);
    }

    public function testUnknownIntentCapturesPendingTrainingCandidate(): void
    {
        $provider = new FakeFallbackProvider('model answer');
        $store = new TrainingCandidateStore();
        $responder = new LocalModelFallbackResponder($provider, $store, ['enabled' => true]);
        $ai = new SynthetIQ(new FakeInterpreter(), new FakeAnalyzer([
            'mystery' => ['unknown.intent' => 1.0],
        ]), null, null, $responder);

        $envelope = $ai->processInputEnvelope('mystery');
        $candidate = $store->pending()[0];

        $this->assertSame('model answer', $envelope['response']);
        $this->assertSame('unknown_intent', $candidate['reason']);
        $this->assertSame('unknown.intent', $candidate['intent']);
        $this->assertSame('pending', $candidate['status']);
        $this->assertSame($candidate['id'], $envelope['fallback']['candidate']['id']);
        $this->assertSame(1, Arr::count($provider->prompts));
    }

    public function testLowConfidenceCapturesFallbackCandidate(): void
    {
        $provider = new FakeFallbackProvider('low confidence answer');
        $store = new TrainingCandidateStore();
        $responder = new LocalModelFallbackResponder($provider, $store, ['enabled' => true]);
        $ai = new SynthetIQ(new FakeInterpreter(), new FakeAnalyzer([
            'hi' => [
                'greeting.intent' => 1.0,
                'status.intent' => 0.9,
            ],
        ]), null, null, $responder, null, [
            'enable_keyword_overlap' => false,
            'enable_naive_bayes' => false,
        ]);
        $ai->setConfidenceThreshold(0.7);
        $ai->addRoute('hello', 'greeting.intent', []);
        $ai->addRoute('All good.', 'status.intent', []);

        $envelope = $ai->processInputEnvelope('hi');
        $candidate = $store->pending()[0];

        $this->assertSame('low confidence answer', $envelope['response']);
        $this->assertSame('low_confidence', $candidate['reason']);
        $this->assertSame('greeting.intent', $candidate['intent']);
        $this->assertSame(['greeting.intent' => 1.0, 'status.intent' => 0.9], $candidate['scores']);
        $this->assertSame($candidate['id'], $envelope['fallback']['candidate']['id']);
    }

    public function testTrainingCandidatesCanBeApprovedOrRejected(): void
    {
        $store = new TrainingCandidateStore();
        $first = $store->capture([
            'prompt' => 'Input: hello',
            'response' => 'Hello',
            'reason' => 'unknown_intent',
        ]);
        $second = $store->capture([
            'prompt' => 'Input: bye',
            'response' => 'Goodbye',
            'reason' => 'low_confidence',
        ]);

        $approved = $store->approve($first['id'], ['reviewer' => 'test']);
        $rejected = $store->reject($second['id'], ['reason' => 'not useful']);

        $this->assertSame('approved', $approved['status']);
        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame(1, Arr::count($store->approved()));
        $this->assertSame(1, Arr::count($store->rejected()));
        $this->assertSame('test', $store->approved()[0]['review']['reviewer']);
    }
}

class FakeFallbackProvider implements FallbackProviderInterface
{
    public array $prompts = [];
    protected ?string $response;

    public function __construct(?string $response)
    {
        $this->response = $response;
    }

    public function complete(string $prompt, Context $context, array $meta = []): ?string
    {
        $this->prompts[] = [
            'prompt' => $prompt,
            'meta' => $meta,
        ];

        return $this->response;
    }
}
