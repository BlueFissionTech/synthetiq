<?php

namespace BlueFission\SynthetIQ\Tests\Intents;

use BlueFission\Arr;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Intent\Matcher;
use BlueFission\SynthetIQ\Intents\IntelligenceRouter;
use BlueFission\SynthetIQ\Tests\Support\MatcherResetter;
use BlueFission\Str;
use PHPUnit\Framework\TestCase;

class IntelligenceRouterTest extends TestCase
{
    protected function setUp(): void
    {
        MatcherResetter::reset();
    }

    public function testRouterScoresAndClassifiesIntent(): void
    {
        $analyzer = new class implements IAnalyzer {
            public function analyze(string $input, Context $context, array $keywords): Arr
            {
                $scores = [];
                $tokens = Str::split(Str::lower($input), ' ');
                foreach ($keywords as $label => $phrases) {
                    foreach ($phrases as $phrase) {
                        $word = $phrase['text'] ?? '';
                        if ($word !== '' && in_array($word, $tokens, true)) {
                            $scores[$label] = ($scores[$label] ?? 0) + (float)($phrase['weight'] ?? 1);
                        }
                    }
                }

                if (!empty($scores)) {
                    arsort($scores);
                }

                return Arr::make($scores);
            }
        };

        $matcher = new Matcher($analyzer);
        $greeting = new Intent('router.greeting', 'Greeting');
        $greeting->addCriteria('keywords', ['word' => 'hello', 'priority' => 10]);
        $matcher->registerIntent($greeting);

        $farewell = new Intent('router.farewell', 'Farewell');
        $farewell->addCriteria('keywords', ['word' => 'bye', 'priority' => 10]);
        $matcher->registerIntent($farewell);

        $router = new IntelligenceRouter($analyzer, $matcher, ['test_size' => 0.0]);
        $context = new Context();

        $scores = $router->score('hello there', $context);
        $this->assertInstanceOf(Arr::class, $scores);
        $this->assertSame('router.greeting', $scores->keys()->get(0));

        $intent = $router->classify('hello there', $context);
        $this->assertNotNull($intent);
        $this->assertSame('router.greeting', $intent->getLabel());
    }

    public function testRouterCanDisableNaiveBayes(): void
    {
        $analyzer = new class implements IAnalyzer {
            public function analyze(string $input, Context $context, array $keywords): Arr
            {
                $scores = [];
                $tokens = Str::split(Str::lower($input), ' ');
                foreach ($keywords as $label => $phrases) {
                    foreach ($phrases as $phrase) {
                        $word = $phrase['text'] ?? '';
                        if ($word !== '' && in_array($word, $tokens, true)) {
                            $scores[$label] = ($scores[$label] ?? 0) + (float)($phrase['weight'] ?? 1);
                        }
                    }
                }

                if (!empty($scores)) {
                    arsort($scores);
                }

                return Arr::make($scores);
            }
        };

        $matcher = new Matcher($analyzer);
        $greeting = new Intent('router.greeting', 'Greeting');
        $greeting->addCriteria('keywords', ['word' => 'hello', 'priority' => 10]);
        $matcher->registerIntent($greeting);

        $router = new IntelligenceRouter($analyzer, $matcher, [
            'test_size' => 0.0,
            'enable_naive_bayes' => false,
        ]);

        $scores = $router->score('hello there', new Context());
        $this->assertInstanceOf(Arr::class, $scores);
        $this->assertSame('router.greeting', $scores->keys()->get(0));
    }

    public function testRouterExposesEnsembleDiagnostics(): void
    {
        $analyzer = $this->createTokenAnalyzer();
        $matcher = $this->createGreetingMatcher($analyzer);
        $router = new IntelligenceRouter($analyzer, $matcher, [
            'test_size' => 0.0,
            'enable_naive_bayes' => false,
        ]);

        $scores = $router->score('hello there', new Context());
        $diagnostics = $router->lastDiagnostics();

        $this->assertInstanceOf(Arr::class, $scores);
        $this->assertArrayHasKey('matcher', $diagnostics['strategies']);
        $this->assertArrayHasKey('keyword_overlap', $diagnostics['strategies']);
        $this->assertSame($scores->toArray(), $diagnostics['combined']);
    }

    public function testStrategyThresholdCanBlockLowScores(): void
    {
        $analyzer = $this->createTokenAnalyzer();
        $matcher = $this->createGreetingMatcher($analyzer);
        $router = new IntelligenceRouter($analyzer, $matcher, [
            'test_size' => 0.0,
            'enable_keyword_overlap' => false,
            'enable_naive_bayes' => false,
            'strategy_thresholds' => [
                'matcher' => 99.0,
            ],
        ]);

        $scores = $router->score('hello there', new Context());
        $intent = $router->classifyFromScores('hello there', new Context(), $scores);

        $this->assertNull($scores);
        $this->assertNull($intent);
        $this->assertTrue($router->lastDiagnostics()['blocked_by_threshold']);
    }

    private function createTokenAnalyzer(): IAnalyzer
    {
        return new class implements IAnalyzer {
            public function analyze(string $input, Context $context, array $keywords): Arr
            {
                $scores = [];
                $tokens = Str::split(Str::lower($input), ' ');
                foreach ($keywords as $label => $phrases) {
                    foreach ($phrases as $phrase) {
                        $word = $phrase['text'] ?? '';
                        if ($word !== '' && in_array($word, $tokens, true)) {
                            $scores[$label] = ($scores[$label] ?? 0) + (float)($phrase['weight'] ?? 1);
                        }
                    }
                }

                if (!empty($scores)) {
                    arsort($scores);
                }

                return Arr::make($scores);
            }
        };
    }

    private function createGreetingMatcher(IAnalyzer $analyzer): Matcher
    {
        $matcher = new Matcher($analyzer);
        $greeting = new Intent('router.greeting', 'Greeting');
        $greeting->addCriteria('keywords', ['word' => 'hello', 'priority' => 10]);
        $matcher->registerIntent($greeting);

        $farewell = new Intent('router.farewell', 'Farewell');
        $farewell->addCriteria('keywords', ['word' => 'bye', 'priority' => 10]);
        $matcher->registerIntent($farewell);

        return $matcher;
    }
}
