<?php

namespace BlueFission\SynthetIQ\Tests;

use BlueFission\Automata\Context;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\ConversationHistory;
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

        $words = array_map(function ($keyword) {
            return $keyword['word'] ?? null;
        }, $criteria['keywords']);

        $this->assertContains('status', $words);
        $this->assertContains('update', $words);
    }

    private function readProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
