<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Acceptance;

use BlueFission\Arr;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\SynthetIQ\Fallback\FallbackResponderInterface;
use BlueFission\SynthetIQ\Fallback\NullFallbackResponder;
use BlueFission\SynthetIQ\Flow\ConversationFlow;
use BlueFission\SynthetIQ\Policy\PolicyDecision;
use BlueFission\SynthetIQ\Policy\PolicyFilterInterface;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Tests\Support\FakeInterpreter;
use BlueFission\SynthetIQ\Tests\Support\MatcherResetter;
use BlueFission\SynthetIQ\Training\RouteTrainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TrainedConversationAcceptanceTest extends TestCase
{
    protected function tearDown(): void
    {
        MatcherResetter::reset();
    }

    public static function scenarios(): iterable
    {
        foreach (require dirname(__DIR__) . '/Fixtures/procedural_conversations.php' as $name => $scenario) {
            yield $name => [$name, $scenario];
        }
    }

    #[DataProvider('scenarios')]
    public function testTrainedTranscriptIsRepeatableWithoutOffload(string $name, array $scenario): void
    {
        [$dialogue, $boosts] = $this->trainingData($scenario);
        $state = RouteTrainer::compile($dialogue, $boosts, ['scenario' => $name, 'version' => 1]);
        $restored = json_decode(json_encode($state, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($state, $restored);

        // Restart from compiled data, not a warm matcher/model or memorized exchange.
        for ($replay = 0; $replay < 2; $replay++) {
            $interpreter = new ScenarioInterpreter();
            $ai = $this->newAI($interpreter, new OffloadTripwire());
            $summary = RouteTrainer::apply($ai, $restored);
            self::assertSame($state['cache_key'], $summary['cache_key']);
            $turn = 0;

            foreach ($scenario['intents'] as $label => $fixture) {
                self::assertFalse($state['intents'][$label . '.reply']['train_statements']);
                foreach ($fixture['held_out'] as $input) {
                    foreach ($scenario['intents'] as $trainingFixture) {
                        self::assertNotContains($input, $trainingFixture['training'], 'Held-out input leaked into training.');
                    }
                    $envelope = $ai->processInputEnvelope($input);
                    self::assertSame($fixture['response'], $envelope['response'], $name . ': ' . $input);
                    self::assertSame($label . '.intent', $envelope['intent']['label']);
                    self::assertSame($label . '.reply', $envelope['response_route']['label']);
                    self::assertFalse($envelope['fallback']['used'], 'Offload cannot count as a trained answer.');
                    self::assertSame('disabled', $envelope['predictor']['status']);
                    self::assertFalse($envelope['policy']['denied']);
                    self::assertSame(++$turn, $envelope['state']['turn']['count']);
                }
            }
            self::assertCount($turn, $interpreter->inputs);
        }
    }

    #[DataProvider('scenarios')]
    public function testTrainingChangesAnUntrainedAnswer(string $name, array $scenario): void
    {
        $ai = $this->newAI(new ScenarioInterpreter(), new NullFallbackResponder());
        $first = reset($scenario['intents']);
        $input = $first['held_out'][0];
        $untrained = $ai->processInputEnvelope($input);
        // An untrained LearningModel may emit an input seed. That is not a learned answer.
        self::assertNotSame($first['response'], $untrained['response']);
        self::assertSame('unknown.intent', $untrained['intent']['label']);
        self::assertSame([], $untrained['intent']['scores']);
        self::assertNull($untrained['response_route']['label']);
        $ai->setFallbackResponder(new OffloadTripwire());
        [$dialogue, $boosts] = $this->trainingData($scenario);
        RouteTrainer::train($ai, $dialogue, $boosts);
        $trained = $ai->processInputEnvelope($input);
        $label = array_key_first($scenario['intents']);
        self::assertSame($first['response'], $trained['response'], $name);
        self::assertSame($label . '.intent', $trained['intent']['label']);
        self::assertSame($label . '.reply', $trained['response_route']['label']);
        self::assertFalse($trained['fallback']['used']);
    }

    #[DataProvider('scenarios')]
    public function testUnknownTurnUsesLocalClarificationNotAPreviousAnswer(string $name, array $scenario): void
    {
        $recovery = new LocalClarification($scenario['recovery']);
        $ai = $this->newAI(new ScenarioInterpreter(), $recovery);
        [$dialogue, $boosts] = $this->trainingData($scenario);
        RouteTrainer::train($ai, $dialogue, $boosts);
        $first = reset($scenario['intents']);
        self::assertSame($first['response'], $ai->processInput($first['held_out'][0]));
        self::assertSame(0, $recovery->calls);

        $envelope = $ai->processInputEnvelope('quasar zephyr obelisk');
        self::assertSame($scenario['recovery'], $envelope['response'], $name);
        self::assertTrue($envelope['fallback']['used']);
        self::assertSame('no_scores', $envelope['fallback']['reason']);
        self::assertSame([], $envelope['intent']['scores']);
        self::assertNull($envelope['response_route']['label']);
        self::assertSame(1, $recovery->calls);
    }

    public function testCorrectedGreetingStillUsesTheTrainedRouteWithoutOffload(): void
    {
        $scenarios = require dirname(__DIR__) . '/Fixtures/procedural_conversations.php';
        $ai = $this->newAI(new ScenarioInterpreter(), new OffloadTripwire());
        [$dialogue, $boosts] = $this->trainingData($scenarios['conversation']);
        RouteTrainer::train($ai, $dialogue, $boosts);
        $ai->enableSpellCorrection(true);

        $envelope = $ai->processInputEnvelope('hellp my friend');
        self::assertSame($scenarios['conversation']['intents']['greeting']['response'], $envelope['response']);
        self::assertSame('greeting.intent', $envelope['intent']['label']);
        self::assertSame('greeting.reply', $envelope['response_route']['label']);
        self::assertSame('hello my friend', $envelope['input']['normalized']);
        self::assertTrue($envelope['correction']['applied']);
        self::assertFalse($envelope['fallback']['used']);
    }

    public function testAmbiguousRequestIsARecoveryNotATrainedAnswer(): void
    {
        $scenarios = require dirname(__DIR__) . '/Fixtures/procedural_conversations.php';
        $recovery = new LocalClarification('Please choose one topic: password help or a refund.');
        $ai = $this->newAI(new ScenarioInterpreter(), $recovery);
        $ai->setConfidenceThreshold(0.8);
        [$dialogue, $boosts] = $this->trainingData($scenarios['support']);
        RouteTrainer::train($ai, $dialogue, $boosts);

        $envelope = $ai->processInputEnvelope('reset password refund request');
        self::assertSame('Please choose one topic: password help or a refund.', $envelope['response']);
        self::assertTrue($envelope['fallback']['used']);
        self::assertSame('low_confidence', $envelope['fallback']['reason']);
        self::assertLessThan(0.8, $envelope['intent']['confidence']);
        self::assertSame(1, $recovery->calls);
    }

    public function testReplHostPolicyRejectsDestructiveAndMixedRequestsBeforeInterpretation(): void
    {
        $scenarios = require dirname(__DIR__) . '/Fixtures/procedural_conversations.php';
        $interpreter = new ScenarioInterpreter();
        $ai = $this->newAI($interpreter, new OffloadTripwire());
        [$dialogue, $boosts] = $this->trainingData($scenarios['synthetic_repl']);
        RouteTrainer::train($ai, $dialogue, $boosts);
        $ai->addPolicyFilter(new class implements PolicyFilterInterface {
            public function inspectInput(string $input, Context $context, array $meta = []): PolicyDecision
            {
                return preg_match('/\b(delete|erase|execute|shell|sudo)\b/i', $input)
                    ? PolicyDecision::deny('simulation_only', 'Simulation: that operation is not permitted; nothing was executed.')
                    : PolicyDecision::allow();
            }

            public function inspectOutput(string $output, Context $context, array $meta = []): PolicyDecision
            {
                return PolicyDecision::allow();
            }
        });

        foreach (['delete everything', 'list files then execute a shell'] as $input) {
            $envelope = $ai->processInputEnvelope($input);
            self::assertSame('Simulation: that operation is not permitted; nothing was executed.', $envelope['response']);
            self::assertTrue($envelope['policy']['denied']);
            self::assertSame('input', $envelope['policy']['stage']);
            self::assertSame('simulation_only', $envelope['policy']['reason']);
            self::assertFalse($envelope['fallback']['used']);
            self::assertSame([], $interpreter->inputs);
        }
    }

    public function testSyntheticNavigationUsesConversationStateWithoutExecutingCommands(): void
    {
        $interpreter = new ScenarioInterpreter();
        $ai = $this->newAI($interpreter, new OffloadTripwire());
        $catalog = [
            'where' => [['pwd', 'current directory'], 'Simulation directory: {context.conversation_flow.current_state}'],
            'listing' => [['ls', 'list directory'], 'Simulation listing for {context.conversation_flow.current_state}.'],
            'enter' => [['cd projects', 'enter projects'], 'Simulation: entered projects.'],
            'home' => [['cd home', 'return home'], 'Simulation: returned home.'],
            'exit' => [['exit', 'quit'], 'Simulation session closed.'],
        ];
        $scenario = ['intents' => []];
        foreach ($catalog as $label => [$training, $response]) {
            $scenario['intents'][$label] = ['training' => $training, 'response' => $response];
        }
        [$dialogue, $boosts] = $this->trainingData($scenario);
        RouteTrainer::train($ai, $dialogue, $boosts);
        $ai->setConversationFlow(ConversationFlow::fromArray([
            'id' => 'synthetic_navigation',
            'start' => 'home',
            'states' => [
                'home' => [
                    'allowed_intents' => ['where.intent', 'listing.intent', 'enter.intent', 'exit.intent'],
                    'transitions' => [
                        'where.intent' => 'home', 'listing.intent' => 'home',
                        'enter.intent' => 'projects', 'exit.intent' => 'closed',
                    ],
                ],
                'projects' => [
                    'allowed_intents' => ['where.intent', 'listing.intent', 'home.intent', 'exit.intent'],
                    'transitions' => [
                        'where.intent' => 'projects', 'listing.intent' => 'projects',
                        'home.intent' => 'home', 'exit.intent' => 'closed',
                    ],
                ],
                'closed' => ['allowed_intents' => [], 'transitions' => [], 'complete' => true],
            ],
        ]));

        $transcript = [
            ['pwd', 'where', 'Simulation directory: home', 'home'],
            ['ls', 'listing', 'Simulation listing for home.', 'home'],
            ['cd projects', 'enter', 'Simulation: entered projects.', 'projects'],
            ['pwd', 'where', 'Simulation directory: projects', 'projects'],
            ['ls', 'listing', 'Simulation listing for projects.', 'projects'],
            ['cd home', 'home', 'Simulation: returned home.', 'home'],
            ['pwd', 'where', 'Simulation directory: home', 'home'],
            ['exit', 'exit', 'Simulation session closed.', 'closed'],
        ];
        foreach ($transcript as [$input, $label, $response, $state]) {
            $envelope = $ai->processInputEnvelope($input);
            self::assertSame($response, $envelope['response']);
            self::assertSame($label . '.intent', $envelope['intent']['label']);
            self::assertSame($label . '.reply', $envelope['response_route']['label']);
            self::assertSame($state, $envelope['flow']['current_state']);
            self::assertSame($state === 'closed' ? 'complete' : 'active', $envelope['flow']['status']);
            self::assertFalse($envelope['fallback']['used']);
        }
        self::assertSame(array_column($transcript, 0), $interpreter->inputs);
    }

    private function newAI(ScenarioInterpreter $interpreter, FallbackResponderInterface $fallback): SynthetIQ
    {
        MatcherResetter::reset();
        $analyzer = new class implements IAnalyzer {
            public function analyze(string $input, Context $context, array $keywords): Arr
            {
                return Arr::make(); // No expected labels/scores injected into the real learned strategy.
            }
        };
        $ai = new SynthetIQ($interpreter, $analyzer, null, null, $fallback, null, [
            'enable_matcher' => false,
            'enable_keyword_overlap' => true,
            'enable_naive_bayes' => false,
        ]);
        $ai->enableSpellCorrection(false);
        $ai->setResponsePredictor(null); // This gate evaluates trained templates, not word generation.

        return $ai;
    }

    private function trainingData(array $scenario): array
    {
        $dialogue = [];
        $boosts = [];
        foreach ($scenario['intents'] as $label => $fixture) {
            $dialogue[$label . '.intent'] = [[$label . '.reply'], $fixture['training'], []];
            $dialogue[$label . '.reply'] = [[], [$fixture['response']], []];
            $boosts[$label . '.reply'] = ['train_statements' => false];
        }

        return [$dialogue, $boosts];
    }
}

final class ScenarioInterpreter extends FakeInterpreter
{
    public array $inputs = [];

    public function __construct()
    {
        parent::__construct(false);
    }

    public function run($code)
    {
        $this->inputs[] = $code;
        return null; // Test boundary only; never execute a host command.
    }
}

final class OffloadTripwire implements FallbackResponderInterface
{
    public function respond(string $input, Context $context, array $meta = []): ?string
    {
        throw new RuntimeException('Fallback was invoked in a trained-answer test.');
    }
}

final class LocalClarification implements FallbackResponderInterface
{
    public int $calls = 0;

    public function __construct(private string $response)
    {
    }

    public function respond(string $input, Context $context, array $meta = []): ?string
    {
        $this->calls++;
        return $this->response;
    }
}
