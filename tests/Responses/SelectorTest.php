<?php

namespace BlueFission\SynthetIQ\Tests\Responses;

use BlueFission\Automata\Context;
use BlueFission\SynthetIQ\Responses\Selector;
use BlueFission\SynthetIQ\Tests\Support\FakePredictor;
use PHPUnit\Framework\TestCase;

class SelectorTest extends TestCase
{
    public function testSelectReturnsEmptyStringWhenNoResponses(): void
    {
        $predictor = new FakePredictor();
        $selector = new Selector($predictor, function () {
            return 1;
        });

        $response = $selector->select('hello', [], new Context());

        $this->assertSame('', $response);
    }

    public function testSelectReturnsOneOfTheResponses(): void
    {
        $predictor = new FakePredictor([
            'hello world' => ['today'],
        ]);
        $selector = new Selector($predictor, function () {
            return 1;
        });

        $responses = ['Hello world.' => 'Hello world.'];
        $response = $selector->select('hello', $responses, new Context());

        $this->assertSame('Hello world.', $response);
    }
}
