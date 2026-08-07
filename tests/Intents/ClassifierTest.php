<?php

namespace BlueFission\SynthetIQ\Tests\Intents;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Intent\Matcher;
use BlueFission\SynthetIQ\Intents\Classifier;
use BlueFission\SynthetIQ\Tests\Support\FakeAnalyzer;
use BlueFission\SynthetIQ\Tests\Support\MatcherResetter;
use PHPUnit\Framework\TestCase;

class ClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        MatcherResetter::reset();
    }

    public function testReturnsMatchedIntentFromAnalyzer(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => ['greeting.intent' => 2],
        ]);

        $matcher = new Matcher($analyzer);
        $intent = new Intent('greeting.intent', 'Greeting', [
            'keywords' => [
                ['word' => 'hello', 'priority' => 3],
            ],
        ]);
        $matcher->registerIntent($intent);

        $classifier = new Classifier($analyzer);
        $result = $classifier->classify('hello', new Context());

        $this->assertNotNull($result);
        $this->assertSame('greeting.intent', $result->getLabel());
    }

    public function testFallsBackToNaiveKeywordMatch(): void
    {
        $analyzer = new FakeAnalyzer([
            'hello' => [],
        ]);

        $matcher = new Matcher($analyzer);
        $intent = new Intent('greeting.intent', 'Greeting', [
            'keywords' => [
                ['word' => '', 'priority' => 1],
                ['word' => 'hello', 'priority' => 3],
            ],
        ]);
        $matcher->registerIntent($intent);

        $classifier = new Classifier($analyzer);
        $result = $classifier->classify('hello', new Context());

        $this->assertNotNull($result);
        $this->assertSame('greeting.intent', $result->getLabel());
    }

    public function testReturnsNullWhenNoMatchFound(): void
    {
        $analyzer = new FakeAnalyzer([
            'unknown' => [],
        ]);

        $classifier = new Classifier($analyzer);
        $result = $classifier->classify('unknown', new Context());

        $this->assertNull($result);
    }
}
