<?php

namespace BlueFission\SynthetIQ\Tests;

use BlueFission\Automata\Context;
use BlueFission\Collections\Collection;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\ConversationHistory;
use BlueFission\SynthetIQ\Fallback\FallbackResponderInterface;
use BlueFission\SynthetIQ\Memory\MemoryAdapterInterface;
use BlueFission\SynthetIQ\Memory\MemoryRecall;
use BlueFission\SynthetIQ\Tests\Support\FakeAnalyzer;
use BlueFission\SynthetIQ\Tests\Support\FakeInterpreter;
use BlueFission\SynthetIQ\Tests\Support\MatcherResetter;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class SynthetIQTest extends TestCase
{
    protected function setUp(): void
    {
        MatcherResetter::reset();
    }

    public function testProcessesInputAndStoresHistory(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => ['greeting.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello there', 'reply.intent', []);

        $response = $ai->processInput('hello');

        $this->assertSame('Hello there', $response);

        $history = $this->readProperty($ai, '_history');
        $this->assertInstanceOf(ConversationHistory::class, $history);

        $entries = $history->getHistory();
        $this->assertCount(1, $entries);
        $entry = $entries->get(0);
        $this->assertSame('hello', $entry['input']);
        $this->assertSame('Hello there', $entry['response']);
    }

    public function testFallsBackToIntentTemplatesWhenNoRoutes(): void
    {
        $analyzer = new FakeAnalyzer([
            'status' => ['status.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $ai->addRoute('All good.', 'status.intent', []);

        $response = $ai->processInput('status');

        $this->assertSame('All good.', $response);
    }

    public function testFallsBackToLastIntentWhenClassifierReturnsNull(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => ['greeting.intent' => 1],
            '???' => [],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello there', 'reply.intent', []);

        $first = $ai->processInput('hello');
        $this->assertSame('Hello there', $first);

        $second = $ai->processInput('???');
        $this->assertSame('Hello there', $second);
    }

    public function testUsesUnknownIntentWhenNoResponses(): void
    {
        $analyzer = new FakeAnalyzer([
            'mystery' => ['unknown.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $ai->addRoute("I'm not sure I understand.", 'unknown.intent', []);

        $response = $ai->processInput('mystery');

        $this->assertSame("I'm not sure I understand.", $response);
    }

    public function testCorrectsMisspelledInput(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => ['greeting.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello there', 'reply.intent', []);

        $response = $ai->processInput('hellp');

        $this->assertSame('Hello there', $response);
        $context = $this->readProperty($ai, '_context');
        $this->assertSame('hellp', $context->get('input_original'));
        $this->assertSame('hello', $context->get('input_corrected'));
    }

    public function testAddIntentKeywordsRegistersCriteria(): void
    {
        $analyzer = new FakeAnalyzer([]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $ai->addIntentKeywords('status.intent', ['status', 'update'], 12);

        $matcher = $this->readProperty($ai, '_matcher');
        $intent = $matcher->getIntent('status.intent');

        $this->assertNotNull($intent);
        $criteria = $intent->getCriteria();
        $this->assertArrayHasKey('keywords', $criteria);

        $words = (new Collection($criteria['keywords']))
            ->map(function ($keyword) {
                return $keyword['word'] ?? null;
            })
            ->toArray();

        $this->assertContains('status', $words);
        $this->assertContains('update', $words);
    }

    public function testResponsePredictorDiagnosticsReportsAvailable(): void
    {
        $analyzer = new FakeAnalyzer([]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $diagnostics = $ai->responsePredictorDiagnostics();

        $this->assertSame('available', $diagnostics['status']);
        $this->assertTrue($diagnostics['can_predict_next_words']);
        $this->assertTrue($diagnostics['can_predict_next_word']);
    }

    public function testResponsePredictorDiagnosticsReportsDisabledFallback(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => ['greeting.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);
        $ai->setResponsePredictor(null);

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello there', 'reply.intent', []);

        $response = $ai->processInput('hello');
        $diagnostics = $ai->responsePredictorDiagnostics();

        $this->assertSame('Hello there', $response);
        $this->assertSame('disabled', $diagnostics['status']);
        $this->assertTrue($diagnostics['fallback_used']);
        $this->assertSame('predictor_disabled', $diagnostics['fallback_reason']);
    }

    public function testResponsePredictorDiagnosticsReportsUnavailablePredictor(): void
    {
        $analyzer = new FakeAnalyzer([]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);
        $ai->setResponsePredictor(new \stdClass());

        $diagnostics = $ai->responsePredictorDiagnostics();

        $this->assertSame('unavailable', $diagnostics['status']);
        $this->assertFalse($diagnostics['can_predict_next_words']);
        $this->assertFalse($diagnostics['can_predict_next_word']);
    }

    public function testResponsePredictorDiagnosticsReportsFailedFallback(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => ['greeting.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);
        $ai->setResponsePredictor(new class {
            public function addSentence($sentence): void
            {
            }

            public function predictNextWords($input): array
            {
                throw new \RuntimeException('predictor offline');
            }
        });

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello there', 'reply.intent', []);

        $response = $ai->processInput('hello');
        $diagnostics = $ai->responsePredictorDiagnostics();

        $this->assertSame('Hello there', $response);
        $this->assertSame('failed', $diagnostics['status']);
        $this->assertTrue($diagnostics['fallback_used']);
        $this->assertSame('predictor_failed', $diagnostics['fallback_reason']);
        $this->assertSame(\RuntimeException::class, $diagnostics['error']['type']);
    }

    public function testProcessInputEnvelopeReportsNormalRoute(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => ['greeting.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello there', 'reply.intent', []);

        $envelope = $ai->processInputEnvelope('hello');

        $this->assertSame('Hello there', $envelope['response']);
        $this->assertSame('hello', $envelope['input']['raw']);
        $this->assertSame('hello', $envelope['input']['normalized']);
        $this->assertSame('reply.intent', $envelope['intent']['label']);
        $this->assertArrayHasKey('reply.intent', $envelope['intent']['scores']);
        $this->assertFalse($envelope['fallback']['used']);
        $this->assertSame('available', $envelope['predictor']['status']);
    }

    public function testProcessInputEnvelopeReportsLowConfidenceFallback(): void
    {
        $analyzer = new FakeAnalyzer([
            'hi' => [
                'greeting.intent' => 1.0,
                'status.intent' => 0.9,
            ],
        ]);
        $interpreter = new FakeInterpreter();
        $fallback = new EnvelopeFallbackResponder('fallback-response');
        $ai = new SynthetIQ($interpreter, $analyzer, null, null, $fallback, null, [
            'enable_keyword_overlap' => false,
            'enable_naive_bayes' => false,
        ]);
        $ai->setConfidenceThreshold(0.7);

        $ai->addRoute('hello', 'greeting.intent', []);
        $ai->addRoute('All good.', 'status.intent', []);

        $envelope = $ai->processInputEnvelope('hi');

        $this->assertSame('fallback-response', $envelope['response']);
        $this->assertSame('greeting.intent', $envelope['intent']['label']);
        $this->assertTrue($envelope['fallback']['used']);
        $this->assertSame('low_confidence', $envelope['fallback']['reason']);
    }

    public function testProcessInputEnvelopeReportsUnknownIntent(): void
    {
        $analyzer = new FakeAnalyzer([
            'mystery' => ['unknown.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $ai->addRoute("I'm not sure I understand.", 'unknown.intent', []);

        $envelope = $ai->processInputEnvelope('mystery');

        $this->assertSame("I'm not sure I understand.", $envelope['response']);
        $this->assertSame('unknown.intent', $envelope['intent']['label']);
        $this->assertSame(['unknown.intent' => 1.0], $envelope['intent']['scores']);
    }

    public function testProcessInputEnvelopeReportsMemoryAssistedRoute(): void
    {
        $analyzer = new FakeAnalyzer([
            'remember' => [
                'status.intent' => 0.4,
                'greeting.intent' => 0.3,
            ],
        ]);
        $interpreter = new FakeInterpreter();
        $memory = new EnvelopeMemoryAdapter(new MemoryRecall(
            [['input' => 'hello', 'response' => 'Hello there']],
            ['greeting.intent' => 1.0],
            ['source' => 'test']
        ));
        $ai = new SynthetIQ($interpreter, $analyzer, null, $memory);

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello there', 'reply.intent', []);
        $ai->addRoute('All good.', 'status.intent', []);

        $envelope = $ai->processInputEnvelope('remember');

        $this->assertSame('Hello there', $envelope['response']);
        $this->assertSame('greeting.intent', $envelope['intent']['label']);
        $this->assertSame([['input' => 'hello', 'response' => 'Hello there']], $envelope['memory']['related']);
        $this->assertSame(['greeting.intent' => 1.0], $envelope['memory']['intentBiases']);
    }

    public function testProcessInputEnvelopeReportsCorrectedInput(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => ['greeting.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello there', 'reply.intent', []);

        $envelope = $ai->processInputEnvelope('hellp');

        $this->assertSame('Hello there', $envelope['response']);
        $this->assertTrue($envelope['correction']['applied']);
        $this->assertSame('hellp', $envelope['correction']['original']);
        $this->assertSame('hello', $envelope['correction']['corrected']);
        $this->assertSame('hello', $envelope['input']['normalized']);
    }

    private function readProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}

class EnvelopeFallbackResponder implements FallbackResponderInterface
{
    private string $response;

    public function __construct(string $response)
    {
        $this->response = $response;
    }

    public function respond(string $input, Context $context, array $meta = []): ?string
    {
        return $this->response;
    }
}

class EnvelopeMemoryAdapter implements MemoryAdapterInterface
{
    private MemoryRecall $recall;

    public function __construct(MemoryRecall $recall)
    {
        $this->recall = $recall;
    }

    public function recordExchange(string $input, string $response, Context $context, array $meta = []): void
    {
    }

    public function recall(string $input, Context $context, array $meta = []): MemoryRecall
    {
        return $this->recall;
    }
}
