<?php

namespace BlueFission\SynthetIQ\Tests\Models;

use BlueFission\SynthetIQ\Models\LearningModel;
use PHPUnit\Framework\TestCase;

class LearningModelTest extends TestCase
{
    public function testReturnsResponseForExactMatch(): void
    {
        $model = new LearningModel();
        $model->observe('hello', 'Hi there!');

        $response = $model->generate('hello');

        $this->assertSame('Hi there!', $response);
    }

    public function testReturnsResponseForSimilarInput(): void
    {
        $model = new LearningModel();
        $model->observe('how are you', "I'm doing well.");

        $response = $model->generate('how are things');

        $this->assertSame("I'm doing well.", $response);
    }

    public function testGeneratesFromMarkovWhenNoMemory(): void
    {
        $model = new LearningModel();
        $model->observe('hello', 'Good to see you.');

        $response = $model->generate('something else');

        $this->assertNotSame('', $response);
    }

    public function testTrainingSkipsIncompleteInteractions(): void
    {
        $model = new LearningModel();
        $model->train([
            ['input' => 'hello'],
            ['output' => 'ignored'],
            ['input' => 'hello', 'output' => 'Hi there!'],
        ]);

        $this->assertSame('Hi there!', $model->generate('hello'));
    }
}
