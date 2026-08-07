<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Handoff;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\SynthetIQ\Profiles\ConversationProfile;
use BlueFission\Val;

class ContextEnvelope extends Obj
{
    public const VERSION = 1;

    protected $_data = [
        'version' => self::VERSION,
        'context_refs' => [],
        'current_intent' => '',
        'unresolved_questions' => [],
        'confidence' => 0.0,
        'provenance' => [],
    ];

    protected $_types = [
        'version' => DataTypes::INTEGER,
        'context_refs' => DataTypes::ARRAY,
        'current_intent' => DataTypes::STRING,
        'unresolved_questions' => DataTypes::ARRAY,
        'confidence' => DataTypes::FLOAT,
        'provenance' => DataTypes::ARRAY,
    ];

    public function __construct(array $context = [])
    {
        parent::__construct();

        $context = Dev::apply('synthetiq.handoff.context.input', $context);
        if (Arr::is($context)) {
            $this->assign(self::normalize($context));
        }
    }

    public static function fromArray(array $context): self
    {
        return new self($context);
    }

    public function currentIntent(): string
    {
        return (string)$this->field('current_intent');
    }

    /**
     * @return array<string, mixed>
     */
    public function contextReferences(): array
    {
        $references = $this->field('context_refs');

        return Arr::is($references) ? $references : [];
    }

    public function boundedFor(ConversationProfile $profile): self
    {
        $context = $this->toArray();
        $context['context_refs'] = [];

        foreach ($this->contextReferences() as $name => $reference) {
            if ($profile->permitsContextReference((string)$name)) {
                $context['context_refs'][$name] = $reference;
            }
        }

        return self::fromArray($context);
    }

    /**
     * @return array<int, string>
     */
    public function redactedKeysFor(ConversationProfile $profile): array
    {
        $redacted = [];
        foreach ($this->contextReferences() as $name => $reference) {
            if (!$profile->permitsContextReference((string)$name)) {
                $redacted[] = (string)$name;
            }
        }

        return $redacted;
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalize(array $context): array
    {
        $confidence = (float)($context['confidence'] ?? 0.0);
        $confidence = (float)Num::max(0.0, Num::min(1.0, $confidence));

        $normalized = [
            'version' => self::VERSION,
            'context_refs' => Arr::is($context['context_refs'] ?? null) ? $context['context_refs'] : [],
            'current_intent' => Str::trim((string)($context['current_intent'] ?? '')),
            'unresolved_questions' => self::stringList($context['unresolved_questions'] ?? []),
            'confidence' => $confidence,
            'provenance' => Arr::is($context['provenance'] ?? null) ? $context['provenance'] : [],
        ];

        $filtered = Dev::apply('synthetiq.handoff.context.normalized', $normalized);

        return Arr::is($filtered) ? $filtered : $normalized;
    }

    /**
     * @param mixed $values
     * @return array<int, string>
     */
    protected static function stringList(mixed $values): array
    {
        if (!Arr::is($values)) {
            return [];
        }

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
