<?php

namespace BlueFission\SynthetIQ\Tests;

use BlueFission\Automata\Context;
use BlueFission\Collections\Collection;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\ConversationHistory;
use BlueFission\SynthetIQ\Fallback\FallbackResponderInterface;
use BlueFission\SynthetIQ\Flow\ConversationFlow;
use BlueFission\SynthetIQ\Memory\MemoryAdapterInterface;
use BlueFission\SynthetIQ\Memory\MemoryRecall;
use BlueFission\SynthetIQ\State\ConversationState;
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

    public function testClassifiesInputWithoutGeneratingResponse(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => ['greeting.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello there', 'reply.intent', []);

        $intent = $ai->classifyInput('hello');

        $this->assertNotNull($intent);
        $this->assertSame('greeting.intent', $intent->getLabel());

        $context = $this->readProperty($ai, '_context');
        $this->assertSame('greeting.intent', $context->get('classified_intent_label'));

        $history = $this->readProperty($ai, '_history');
        $this->assertSame(0, $history->getHistory()->count());
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
        $this->assertSame('greeting.intent', $envelope['intent']['label']);
        $this->assertArrayHasKey('greeting.intent', $envelope['intent']['scores']);
        $this->assertSame('reply.intent', $envelope['response_route']['label']);
        $this->assertArrayHasKey('reply.intent', $envelope['response_route']['scores']);
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
        $this->assertSame('reply.intent', $envelope['memory']['selection']['selected_intent']);
        $this->assertSame(1, $envelope['memory']['selection']['related_count']);
        $this->assertSame(1, $envelope['memory']['selection']['matched_count']);
        $this->assertSame('hello', $envelope['memory']['selection']['matches'][0]['input']);
    }

    public function testMemoryRecallResponseContextSupportsContextEntries(): void
    {
        $analyzer = new FakeAnalyzer([
            'remember' => [
                'status.intent' => 0.4,
                'greeting.intent' => 0.3,
            ],
        ]);
        $interpreter = new FakeInterpreter();
        $entry = new Context();
        $entry->set('input', 'hello');
        $entry->set('response', 'Hello there');
        $entry->set('intent_label', 'reply.intent');
        $entry->set('scope', 'session-a');
        $entry->set('user_id', 'user-a');
        $entry->set('session_id', 'session-a');
        $memory = new EnvelopeMemoryAdapter(new MemoryRecall(
            [
                'episode-a' => [
                    'context' => $entry,
                    'similarity' => 0.9,
                ],
            ],
            ['greeting.intent' => 1.0],
            ['scope' => 'session-a']
        ));
        $ai = new SynthetIQ($interpreter, $analyzer, null, $memory);

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello there', 'reply.intent', []);
        $ai->addRoute('All good.', 'status.intent', []);

        $envelope = $ai->processInputEnvelope('remember');

        $this->assertSame('Hello there', $envelope['response']);
        $this->assertSame(1, $envelope['memory']['selection']['related_count']);
        $this->assertSame(1, $envelope['memory']['selection']['matched_count']);
        $this->assertSame('episode-a', $envelope['memory']['selection']['matches'][0]['label']);
        $this->assertSame('session-a', $envelope['memory']['selection']['matches'][0]['scope']);
        $this->assertSame(['scope' => 'session-a'], $envelope['memory']['selection']['meta']);
    }

    public function testIrrelevantMemoryRecallDoesNotClaimSelectionMatch(): void
    {
        $analyzer = new FakeAnalyzer([
            'status' => ['status.intent' => 1.0],
        ]);
        $interpreter = new FakeInterpreter();
        $memory = new EnvelopeMemoryAdapter(new MemoryRecall(
            [
                [
                    'input' => 'hello',
                    'response' => 'Hello there',
                    'intent_label' => 'reply.intent',
                ],
            ],
            [],
            ['source' => 'test']
        ));
        $ai = new SynthetIQ($interpreter, $analyzer, null, $memory);

        $ai->addRoute('All good.', 'status.intent', []);

        $envelope = $ai->processInputEnvelope('status');

        $this->assertSame('All good.', $envelope['response']);
        $this->assertSame(1, $envelope['memory']['selection']['related_count']);
        $this->assertSame(0, $envelope['memory']['selection']['matched_count']);
        $this->assertSame([], $envelope['memory']['selection']['matches']);
    }

    public function testEmptyMemoryRecallKeepsEnvelopeSelectionEmpty(): void
    {
        $analyzer = new FakeAnalyzer([
            'status' => ['status.intent' => 1.0],
        ]);
        $interpreter = new FakeInterpreter();
        $memory = new EnvelopeMemoryAdapter(new MemoryRecall());
        $ai = new SynthetIQ($interpreter, $analyzer, null, $memory);

        $ai->addRoute('All good.', 'status.intent', []);

        $envelope = $ai->processInputEnvelope('status');

        $this->assertSame('All good.', $envelope['response']);
        $this->assertSame([], $envelope['memory']['selection']);
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

    public function testScriptedTemplateDiagnosticsAreIncludedInEnvelope(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => ['greeting.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);
        $ai->enableScriptedTemplates(true);

        $ai->addRoute('hello', 'greeting.intent', ['reply.intent']);
        $ai->addRoute('Hello {= upper(input) }', 'reply.intent', []);

        $envelope = $ai->processInputEnvelope('hello');

        $this->assertSame('Hello HELLO', $envelope['response']);
        $this->assertTrue($envelope['templates']['scripted']['enabled']);
        $this->assertSame('upper(input)', $envelope['templates']['scripted']['blocks'][0]['expression']);
    }

    public function testConversationFlowConstrainsRoutingAndAdvancesState(): void
    {
        $analyzer = new FakeAnalyzer([
            'ship' => [
                'general.intent' => 1.0,
                'shipping.intent' => 0.3,
            ],
            'confirm' => [
                'confirm.intent' => 1.0,
            ],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);
        $ai->setConversationFlow(ConversationFlow::fromArray(require dirname(__DIR__) . '/sample_configs/conversation_flow.php'));

        $ai->addRoute('ship', 'shipping.intent', ['shipping.reply']);
        $ai->addRoute('Shipping selected.', 'shipping.reply', []);
        $ai->addRoute('confirm', 'confirm.intent', ['confirm.reply']);
        $ai->addRoute('Confirmed.', 'confirm.reply', []);

        $first = $ai->processInputEnvelope('ship');

        $this->assertSame('Shipping selected.', $first['response']);
        $this->assertSame('shipping.intent', $first['intent']['label']);
        $this->assertSame('shipping.reply', $first['response_route']['label']);
        $this->assertSame('shipping_details', $first['flow']['current_state']);
        $this->assertSame('active', $first['flow']['status']);
        $this->assertSame('shipping.intent', $first['flow']['last_transition']['intent']);

        $second = $ai->processInputEnvelope('confirm');

        $this->assertSame('Confirmed.', $second['response']);
        $this->assertSame('complete', $second['flow']['current_state']);
        $this->assertSame('complete', $second['flow']['status']);

        $ai->resetConversationFlow();
        $this->assertSame('choose_topic', $ai->conversationFlow()->currentStateId());

        $ai->abandonConversationFlow();
        $this->assertTrue($ai->conversationFlow()->isAbandoned());
    }

    public function testConversationFlowUsesRecoveryIntentWhenInputFallsOutsideActiveState(): void
    {
        $analyzer = new FakeAnalyzer([
            'unknown' => [
                'general.intent' => 1.0,
            ],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);
        $ai->setConversationFlow(ConversationFlow::fromArray(require dirname(__DIR__) . '/sample_configs/conversation_flow.php'));

        $ai->addRoute('recover', 'flow.recovery.intent', ['recovery.reply']);
        $ai->addRoute('Please choose shipping or account.', 'recovery.reply', []);

        $envelope = $ai->processInputEnvelope('unknown');

        $this->assertSame('Please choose shipping or account.', $envelope['response']);
        $this->assertSame('flow.recovery.intent', $envelope['intent']['label']);
        $this->assertSame('recovery.reply', $envelope['response_route']['label']);
        $this->assertSame('choose_topic', $envelope['flow']['current_state']);
        $this->assertSame('flow.recovery.intent', $envelope['flow']['last_transition']['intent']);
        $this->assertTrue((bool)$envelope['flow']['last_transition']['fallback']);
    }

    public function testConversationStateInfluencesContextAndResponseEnvelope(): void
    {
        $analyzer = new FakeAnalyzer([
            'status' => ['status.intent' => 1],
        ]);
        $interpreter = new FakeInterpreter();
        $ai = new SynthetIQ($interpreter, $analyzer);
        $ai->setConversationState(
            ConversationState::fromArray([
                'persona' => [
                    'name' => 'Guide',
                    'role' => 'assistant',
                    'traits' => ['bounded'],
                ],
                'tone' => 'calm',
                'mood' => 'steady',
                'task' => [
                    'state' => 'checking',
                    'slots' => ['goal' => 'status'],
                ],
                'session' => [
                    'id' => 'session-1',
                    'user_id' => 'user-1',
                    'scope' => 'state-test',
                ],
            ])
        );

        $ai->addRoute('status', 'status.intent', ['reply.intent']);
        $ai->addRoute('Tone: {{context.tone}}', 'reply.intent', []);

        $envelope = $ai->processInputEnvelope('status');

        $this->assertStringContainsString('calm', $envelope['response']);
        $this->assertSame('calm', $envelope['state']['tone']);
        $this->assertSame('checking', $envelope['state']['task']['state']);
        $this->assertSame('status', $envelope['state']['task']['slots']['goal']);
        $this->assertSame(1, $envelope['state']['turn']['count']);
        $this->assertSame('status.intent', $envelope['state']['turn']['last_intent']);

        $ai->resetConversationState();
        $this->assertSame('idle', $ai->conversationState()->toArray()['task']['state']);
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
