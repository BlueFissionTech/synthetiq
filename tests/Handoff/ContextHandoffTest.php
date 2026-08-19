<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Handoff;

use BlueFission\SynthetIQ\Handoff\ContextEnvelope;
use BlueFission\SynthetIQ\Handoff\ContextHandoff;
use BlueFission\SynthetIQ\Handoff\HandoffResult;
use BlueFission\SynthetIQ\Profiles\ConversationProfile;
use BlueFission\SynthetIQ\Profiles\ProfileRegistry;
use PHPUnit\Framework\TestCase;

final class ContextHandoffTest extends TestCase
{
    public function testSelectsTheFirstCompatibleProfile(): void
    {
        $other = $this->profile();
        $other['id'] = 'sales-guide';
        $other['identity']['name'] = 'Sales Guide';
        $other['supported_intents'] = ['sales.qualify'];

        $registry = new ProfileRegistry([
            ConversationProfile::fromArray($other),
            ConversationProfile::fromArray($this->profile()),
        ]);

        $selected = $registry->selectFor('support.status', ['conversation.classify']);

        $this->assertInstanceOf(ConversationProfile::class, $selected);
        $this->assertSame('support-guide', $selected->id());
        $this->assertNull($registry->selectFor('support.status', ['filesystem.write']));
    }

    public function testAcceptsAndBoundsAValidProfileContextHandoff(): void
    {
        $profile = ConversationProfile::fromArray($this->profile());
        $context = ContextEnvelope::fromArray($this->context());
        $handoff = new ContextHandoff();

        $result = $handoff->handoff($profile, $context, ['conversation.classify']);
        $repeated = $handoff->handoff($profile, $context, ['conversation.classify']);

        $this->assertTrue($profile->isValid());
        $this->assertTrue($result->isAccepted());
        $this->assertSame(HandoffResult::STATUS_ACCEPTED, $result->status());
        $this->assertSame(
            ['conversation_history' => 'history:turn-4', 'memory' => 'memory:episode-2'],
            $result->context()['context_refs']
        );
        $this->assertContains('redacted_context_ref:private_notes', $result->diagnostics());
        $this->assertSame($result->outputId(), $repeated->outputId());
        $this->assertStringStartsWith('handoff:', $result->outputId());
    }

    public function testRejectsUndeclaredCapabilityWithoutLeakingContext(): void
    {
        $result = (new ContextHandoff())->handoff(
            ConversationProfile::fromArray($this->profile()),
            ContextEnvelope::fromArray($this->context()),
            ['filesystem.write']
        );

        $this->assertSame(HandoffResult::STATUS_REJECTED, $result->status());
        $this->assertSame([], $result->context());
        $this->assertContains('undeclared_capability:filesystem.write', $result->diagnostics());
    }

    public function testRequestsClarificationWhenCurrentIntentIsMissing(): void
    {
        $context = $this->context();
        $context['current_intent'] = '';

        $result = (new ContextHandoff())->handoff(
            ConversationProfile::fromArray($this->profile()),
            ContextEnvelope::fromArray($context)
        );

        $this->assertSame(HandoffResult::STATUS_CLARIFICATION, $result->status());
        $this->assertContains('current_intent_required', $result->diagnostics());
    }

    public function testFailsForAnInvalidTargetProfile(): void
    {
        $result = (new ContextHandoff())->handoff(
            ConversationProfile::fromArray(['id' => 'incomplete']),
            ContextEnvelope::fromArray($this->context())
        );

        $this->assertSame(HandoffResult::STATUS_FAILURE, $result->status());
        $this->assertContains('profile:role_required', $result->diagnostics());
        $this->assertSame([], $result->context());
    }

    public function testResumesAnAcceptedBoundedContext(): void
    {
        $profile = ConversationProfile::fromArray($this->profile());
        $handoff = new ContextHandoff();
        $first = $handoff->handoff($profile, ContextEnvelope::fromArray($this->context()));
        $resumed = $handoff->handoff($profile, ContextEnvelope::fromArray($first->context()));

        $this->assertTrue($resumed->isAccepted());
        $this->assertSame(['confirm delivery window'], $resumed->context()['unresolved_questions']);
        $this->assertSame($first->outputId(), $resumed->outputId());
    }

    public function testPublishedOutcomeFixturesRemainDeterministicAndSideEffectFree(): void
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/conversation_profiles.php';

        foreach ($config['outcome_fixtures'] as $name => $fixture) {
            $profileData = $fixture['profile_data']
                ?? $config['profiles'][$fixture['profile']];
            $profile = ConversationProfile::fromArray($profileData);
            $context = ContextEnvelope::fromArray($fixture['context']);
            $handoff = new ContextHandoff();

            $first = $handoff->handoff($profile, $context, $fixture['required_capabilities']);
            $repeated = $handoff->handoff($profile, $context, $fixture['required_capabilities']);

            $this->assertSame($fixture['expected_status'], $first->status(), $name);
            $this->assertSame($first->toArray(), $repeated->toArray(), $name);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/conversation_profiles.php';

        return $config['profiles']['support-guide'];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/conversation_profiles.php';

        return $config['handoff']['context'];
    }
}
