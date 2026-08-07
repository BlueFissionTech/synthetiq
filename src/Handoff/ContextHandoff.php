<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Handoff;

use BlueFission\Arr;
use BlueFission\Behavioral\Behaviors\Action;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\Security\Hash;
use BlueFission\Str;
use BlueFission\SynthetIQ\Profiles\ConversationProfile;
use BlueFission\Val;

class ContextHandoff extends Obj
{
    /**
     * @param array<int, string> $requiredCapabilities
     */
    public function handoff(
        ConversationProfile $profile,
        ContextEnvelope $context,
        array $requiredCapabilities = []
    ): HandoffResult {
        $requiredCapabilities = Dev::apply(
            'synthetiq.handoff.required_capabilities',
            self::stringList($requiredCapabilities)
        );
        $requiredCapabilities = Arr::is($requiredCapabilities) ? $requiredCapabilities : [];

        $this->perform(new Action(Action::PROCESS), new Meta(data: [
            'profile_id' => $profile->id(),
            'required_capabilities' => $requiredCapabilities,
        ]));

        $profileErrors = $profile->errors();
        if (Val::isNotEmpty($profileErrors)) {
            return $this->result(
                HandoffResult::STATUS_FAILURE,
                $profile,
                [],
                Arr::make($profileErrors)->map(static fn(string $error): string => 'profile:' . $error)->toArray()
            );
        }

        if (Val::isEmpty($context->currentIntent())) {
            return $this->result(
                HandoffResult::STATUS_CLARIFICATION,
                $profile,
                [],
                ['current_intent_required']
            );
        }

        if (!$profile->supportsIntent($context->currentIntent())) {
            return $this->result(
                HandoffResult::STATUS_REJECTED,
                $profile,
                [],
                ['unsupported_intent:' . $context->currentIntent()]
            );
        }

        $diagnostics = [];
        foreach ($requiredCapabilities as $capability) {
            if (!$profile->declaresCapability((string)$capability)) {
                $diagnostics[] = 'undeclared_capability:' . Str::trim((string)$capability);
            }
        }

        if (Val::isNotEmpty($diagnostics)) {
            return $this->result(HandoffResult::STATUS_REJECTED, $profile, [], $diagnostics);
        }

        $bounded = $context->boundedFor($profile);
        foreach ($context->redactedKeysFor($profile) as $key) {
            $diagnostics[] = 'redacted_context_ref:' . $key;
        }

        return $this->result(
            HandoffResult::STATUS_ACCEPTED,
            $profile,
            $bounded->toArray(),
            $diagnostics
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, string> $diagnostics
     */
    protected function result(
        string $status,
        ConversationProfile $profile,
        array $context,
        array $diagnostics
    ): HandoffResult {
        $payload = [
            'handoff_status' => $status,
            'profile_id' => $profile->id(),
            'context' => $context,
        ];
        $digest = Hash::value($payload, 'sha256');
        $outputId = 'handoff:' . Str::sub($digest, 0, 16);
        $result = new HandoffResult($status, $profile->id(), $context, $diagnostics, $outputId);

        $this->perform(
            $status === HandoffResult::STATUS_FAILURE ? Event::FAILURE : Event::SUCCESS,
            new Meta(data: $result->toArray())
        );
        $this->perform(Event::PROCESSED, new Meta(data: $result->toArray()));
        Dev::do('synthetiq.handoff.' . $status, $result->toArray());
        Dev::do('synthetiq.handoff.completed', $result->toArray());

        return $result;
    }

    /**
     * @return array<int, string>
     */
    protected static function stringList(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            $value = Str::trim((string)$value);
            if (Val::isNotEmpty($value) && !Arr::has($list, $value, true)) {
                $list[] = $value;
            }
        }

        return $list;
    }
}
