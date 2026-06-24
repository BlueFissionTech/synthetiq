<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Flow;

use BlueFission\Arr;
use BlueFission\SynthetIQ\Flow\ConversationFlow;
use PHPUnit\Framework\TestCase;

final class ConversationFlowTest extends TestCase
{
    public function testSampleFlowIsValidAndConstrainsScores(): void
    {
        $flow = ConversationFlow::fromArray(require dirname(__DIR__, 2) . '/sample_configs/conversation_flow.php');

        $this->assertSame([], ConversationFlow::validate($flow->toArray()['definition']));
        $this->assertSame('choose_topic', $flow->currentStateId());

        $scores = $flow->constrainScores(Arr::make([
            'general.intent' => 1.0,
            'shipping.intent' => 0.4,
        ]));

        $this->assertSame(['shipping.intent' => 0.4], $scores->toArray());
    }

    public function testFallbackIntentIsUsedWhenAllowedScoresAreMissing(): void
    {
        $flow = ConversationFlow::fromArray(require dirname(__DIR__, 2) . '/sample_configs/conversation_flow.php');

        $scores = $flow->constrainScores(Arr::make([
            'general.intent' => 1.0,
        ]));

        $this->assertSame(['flow.recovery.intent' => 1.0], $scores->toArray());
    }

    public function testAdvancesResetsCompletesAndAbandonsFlow(): void
    {
        $flow = ConversationFlow::fromArray(require dirname(__DIR__, 2) . '/sample_configs/conversation_flow.php');

        $flow->advance('shipping.intent');
        $this->assertSame('shipping_details', $flow->currentStateId());
        $this->assertTrue($flow->isActive());

        $flow->advance('confirm.intent');
        $this->assertSame('complete', $flow->currentStateId());
        $this->assertTrue($flow->isComplete());

        $flow->reset();
        $this->assertSame('choose_topic', $flow->currentStateId());
        $this->assertTrue($flow->isActive());

        $flow->abandon();
        $this->assertTrue($flow->isAbandoned());
    }

    public function testValidationReportsMissingTargets(): void
    {
        $errors = ConversationFlow::validate([
            'start' => 'intro',
            'states' => [
                'intro' => [
                    'transitions' => ['next.intent' => 'missing'],
                    'fallback' => 'missing_fallback',
                ],
            ],
        ]);

        $this->assertContains('Flow state intro transition next.intent points to missing state missing.', $errors);
        $this->assertContains('Flow state intro fallback points to missing state missing_fallback.', $errors);
    }
}
