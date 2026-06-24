<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Scenes;

use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;

class SceneContract
{
    public const VERSION = 1;

    public const TYPE_DIALOGUE = 'dialogue';
    public const TYPE_DECISION = 'decision';
    public const TYPE_HANDOFF = 'handoff';

    /**
     * @return array<int, string>
     */
    public static function validate(array $scene): array
    {
        $errors = [];
        $id = self::text($scene['id'] ?? '');
        if (Val::isEmpty($id)) {
            $errors[] = 'Scene id is required.';
        }

        if (!Arr::is($scene['voice_policy'] ?? null)) {
            $errors[] = 'Scene voice_policy is required.';
        } else {
            $errors = Arr::merge($errors, self::validateVoicePolicy($scene['voice_policy']));
        }

        if (!Arr::is($scene['public_safety'] ?? null)) {
            $errors[] = 'Scene public_safety is required.';
        } else {
            $errors = Arr::merge($errors, self::validatePublicSafety($scene['public_safety']));
        }

        $states = Arr::is($scene['states'] ?? null) ? $scene['states'] : [];
        if (Val::isEmpty($states)) {
            $errors[] = 'Scene states are required.';
            return $errors;
        }

        $stateIds = [];
        foreach ($states as $index => $state) {
            if (!Arr::is($state)) {
                $errors[] = "Scene state {$index} must be an array.";
                continue;
            }

            $stateId = self::text($state['id'] ?? '');
            if (Val::isEmpty($stateId)) {
                $errors[] = "Scene state {$index} is missing id.";
                continue;
            }

            $stateIds[$stateId] = true;
            $errors = Arr::merge($errors, self::validateState($state, $stateId));
        }

        foreach ($states as $state) {
            if (Arr::is($state)) {
                $errors = Arr::merge($errors, self::validateTransitions($state, $stateIds));
            }
        }

        return $errors;
    }

    public static function isValid(array $scene): bool
    {
        return Val::isEmpty(self::validate($scene));
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalize(array $scene): array
    {
        $states = [];
        foreach ((Arr::is($scene['states'] ?? null) ? $scene['states'] : []) as $state) {
            if (!Arr::is($state)) {
                continue;
            }

            $states[] = self::normalizeState($state);
        }

        return [
            'version' => self::VERSION,
            'id' => self::text($scene['id'] ?? ''),
            'title' => self::text($scene['title'] ?? ''),
            'summary' => self::text($scene['summary'] ?? ''),
            'voice_policy' => Arr::is($scene['voice_policy'] ?? null) ? $scene['voice_policy'] : [],
            'public_safety' => Arr::is($scene['public_safety'] ?? null) ? $scene['public_safety'] : [],
            'handoff' => Arr::is($scene['handoff'] ?? null) ? $scene['handoff'] : [],
            'states' => $states,
        ];
    }

    /**
     * @param array<string, mixed> $scene
     * @return array<string, array<string, mixed>>
     */
    public static function statesById(array $scene): array
    {
        $states = [];
        foreach ((Arr::is($scene['states'] ?? null) ? $scene['states'] : []) as $state) {
            if (!Arr::is($state)) {
                continue;
            }

            $id = self::text($state['id'] ?? '');
            if (Val::isNotEmpty($id)) {
                $states[$id] = $state;
            }
        }

        return $states;
    }

    /**
     * @return array<int, string>
     */
    protected static function validateVoicePolicy(array $policy): array
    {
        $errors = [];
        if (Val::isEmpty(self::text($policy['tone'] ?? ''))) {
            $errors[] = 'Scene voice_policy.tone is required.';
        }

        $allowed = Arr::is($policy['allowed'] ?? null) ? $policy['allowed'] : [];
        $avoid = Arr::is($policy['avoid'] ?? null) ? $policy['avoid'] : [];
        if (Val::isEmpty($allowed) && Val::isEmpty($avoid)) {
            $errors[] = 'Scene voice_policy must define allowed or avoid guidance.';
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    protected static function validatePublicSafety(array $policy): array
    {
        $errors = [];
        if (!Arr::is($policy['constraints'] ?? null) || Val::isEmpty($policy['constraints'])) {
            $errors[] = 'Scene public_safety.constraints are required.';
        }

        if (!Arr::is($policy['escalation'] ?? null)) {
            $errors[] = 'Scene public_safety.escalation is required.';
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    protected static function validateState(array $state, string $stateId): array
    {
        $errors = [];
        $type = self::text($state['type'] ?? self::TYPE_DIALOGUE);
        if (!Arr::contains([self::TYPE_DIALOGUE, self::TYPE_DECISION, self::TYPE_HANDOFF], $type, true)) {
            $errors[] = "Scene state {$stateId} has unsupported type.";
        }

        if (Val::isEmpty(self::text($state['prompt'] ?? '')) && $type !== self::TYPE_HANDOFF) {
            $errors[] = "Scene state {$stateId} prompt is required.";
        }

        if (!Arr::is($state['fallback'] ?? null)) {
            $errors[] = "Scene state {$stateId} fallback is required.";
        }

        if ($type === self::TYPE_DECISION) {
            $choices = Arr::is($state['choices'] ?? null) ? $state['choices'] : [];
            if (Val::isEmpty($choices)) {
                $errors[] = "Scene decision state {$stateId} requires choices.";
            }
        }

        if ($type === self::TYPE_HANDOFF && !Arr::is($state['handoff'] ?? null)) {
            $errors[] = "Scene handoff state {$stateId} requires handoff metadata.";
        }

        return $errors;
    }

    /**
     * @param array<string, bool> $stateIds
     * @return array<int, string>
     */
    protected static function validateTransitions(array $state, array $stateIds): array
    {
        $errors = [];
        $stateId = self::text($state['id'] ?? '');
        $choices = Arr::is($state['choices'] ?? null) ? $state['choices'] : [];
        foreach ($choices as $index => $choice) {
            if (!Arr::is($choice)) {
                $errors[] = "Scene state {$stateId} choice {$index} must be an array.";
                continue;
            }

            $choiceId = self::text($choice['id'] ?? '');
            if (Val::isEmpty($choiceId)) {
                $errors[] = "Scene state {$stateId} choice {$index} is missing id.";
            }

            $next = self::text($choice['next'] ?? '');
            if (Val::isNotEmpty($next) && !Arr::hasKey($stateIds, $next)) {
                $errors[] = "Scene state {$stateId} choice {$choiceId} points to missing state {$next}.";
            }
        }

        $fallback = Arr::is($state['fallback'] ?? null) ? $state['fallback'] : [];
        $fallbackNext = self::text($fallback['next'] ?? '');
        if (Val::isNotEmpty($fallbackNext) && !Arr::hasKey($stateIds, $fallbackNext)) {
            $errors[] = "Scene state {$stateId} fallback points to missing state {$fallbackNext}.";
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function normalizeState(array $state): array
    {
        return [
            'id' => self::text($state['id'] ?? ''),
            'type' => self::text($state['type'] ?? self::TYPE_DIALOGUE),
            'prompt' => self::text($state['prompt'] ?? ''),
            'choices' => Arr::is($state['choices'] ?? null) ? $state['choices'] : [],
            'fallback' => Arr::is($state['fallback'] ?? null) ? $state['fallback'] : [],
            'handoff' => Arr::is($state['handoff'] ?? null) ? $state['handoff'] : [],
            'metadata' => Arr::is($state['metadata'] ?? null) ? $state['metadata'] : [],
        ];
    }

    protected static function text(mixed $value): string
    {
        return Str::trim((string)$value);
    }
}
