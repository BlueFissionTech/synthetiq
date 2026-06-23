<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Training;

use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Tests\Support\FakeAnalyzer;
use BlueFission\SynthetIQ\Tests\Support\FakeInterpreter;
use BlueFission\SynthetIQ\Tests\Support\MatcherResetter;
use BlueFission\SynthetIQ\Training\RouteTrainer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class RouteTrainerTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        MatcherResetter::reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    public function testTrainsRoutesAndEmitsProgressEvents(): void
    {
        $ai = new SynthetIQ(
            new FakeInterpreter(),
            new FakeAnalyzer([
                'hello' => ['greeting.intent' => 1],
            ]),
            null,
            null,
            null,
            null,
            ['enable_naive_bayes' => false]
        );

        $dialogue = [
            'greeting.intent' => [
                ['reply.intent'],
                ['hello', 'hi there'],
                ['hello', 'greeting', 'what'],
            ],
            'reply.intent' => [
                [],
                ['Hello there'],
                ['reply'],
            ],
        ];

        $boosts = [
            'greeting.intent' => [
                'keywords' => ['welcome'],
                'exclude' => ['greeting'],
                'priority' => 14,
            ],
        ];

        $events = [];
        $result = RouteTrainer::train($ai, $dialogue, $boosts, static function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $this->assertSame(2, $result['intents']);
        $this->assertSame(3, $result['routes']);
        $this->assertSame(3, $result['keywords']);
        $this->assertSame(RouteTrainer::cacheKey($dialogue, $boosts), $result['cache_key']);

        $routeEvents = array_values(array_filter(
            $events,
            static fn(array $event): bool => ($event['stage'] ?? null) === RouteTrainer::STAGE_ROUTE
        ));
        $this->assertCount(3, $routeEvents);
        $this->assertSame(1, $routeEvents[0]['current']);
        $this->assertSame(3, $routeEvents[2]['current']);
        $this->assertSame(RouteTrainer::STAGE_COMPLETE, $events[array_key_last($events)]['stage']);

        $matcher = $this->readProperty($ai, '_matcher');
        $intent = $matcher->getIntent('greeting.intent');
        $criteria = $intent->getCriteria();
        $words = array_map(static fn($keyword): string => (string)($keyword['word'] ?? ''), $criteria['keywords']);

        $this->assertContains('hello', $words);
        $this->assertContains('welcome', $words);
        $this->assertNotContains('what', $words);
        $this->assertNotContains('greeting', $words);
    }

    public function testCacheKeyIsStableForAssociativeCatalogOrdering(): void
    {
        $dialogue = [
            'b.intent' => [[], ['beta'], ['b']],
            'a.intent' => [[], ['alpha'], ['a']],
        ];
        $reorderedDialogue = [
            'a.intent' => [[], ['alpha'], ['a']],
            'b.intent' => [[], ['beta'], ['b']],
        ];

        $boosts = [
            'b.intent' => ['keywords' => ['bee']],
            'a.intent' => ['keywords' => ['aye']],
        ];
        $reorderedBoosts = [
            'a.intent' => ['keywords' => ['aye']],
            'b.intent' => ['keywords' => ['bee']],
        ];

        $this->assertSame(
            RouteTrainer::cacheKey($dialogue, $boosts, ['grammar' => ['v' => 1]]),
            RouteTrainer::cacheKey($reorderedDialogue, $reorderedBoosts, ['grammar' => ['v' => 1]])
        );
    }

    public function testCompilesSavesLoadsAndAppliesRouteState(): void
    {
        $dialogue = [
            'greeting.intent' => [
                ['reply.intent'],
                ['hello', '', 'hi there'],
                ['hello', 'what'],
            ],
            'reply.intent' => [
                [],
                ['Hello there'],
                ['reply'],
            ],
        ];
        $boosts = [
            'greeting.intent' => [
                'keywords' => ['welcome'],
            ],
        ];
        $extra = [
            'grammar' => ['version' => 1],
        ];

        $state = RouteTrainer::compile($dialogue, $boosts, $extra);

        $this->assertSame(RouteTrainer::STATE_VERSION, $state['version']);
        $this->assertTrue(RouteTrainer::stateMatches($state, $dialogue, $boosts, $extra));
        $this->assertFalse(RouteTrainer::stateMatches($state, $dialogue, $boosts, ['grammar' => ['version' => 2]]));
        $this->assertSame(2, $state['meta']['intents']);
        $this->assertSame(3, $state['meta']['routes']);

        $path = $this->tempPath();
        RouteTrainer::saveState($state, $path);
        $loaded = RouteTrainer::loadState($path);

        $this->assertSame($state, $loaded);

        $ai = new SynthetIQ(
            new FakeInterpreter(),
            new FakeAnalyzer([
                'hello' => ['greeting.intent' => 1],
            ]),
            null,
            null,
            null,
            null,
            ['enable_naive_bayes' => false]
        );

        $events = [];
        $summary = RouteTrainer::apply($ai, $loaded, static function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $this->assertSame(3, $summary['routes']);
        $this->assertSame($state['cache_key'], $summary['cache_key']);
        $this->assertSame('Hello there', $ai->processInput('hello'));
        $this->assertSame(RouteTrainer::STAGE_COMPLETE, $events[array_key_last($events)]['stage']);
    }

    public function testNormalizeKeywordsRemovesStopwordsExcludesAndDuplicates(): void
    {
        $this->assertSame(
            ['hello', 'world', 'the'],
            RouteTrainer::normalizeKeywords([' Hello ', 'world', 'hello', 'the', 'skip'], ['skip'])
        );
    }

    private function readProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    private function tempPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'synthetiq_routes_');
        $this->assertIsString($path);
        $this->tempFiles[] = $path;

        return $path;
    }
}
