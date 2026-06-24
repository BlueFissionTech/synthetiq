<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\State;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Str;

class ConversationState
{
    /**
     * @var array<string, mixed>
     */
    protected array $_data = [];

    public function __construct(array $state = [])
    {
        $this->reset();
        if (Arr::isNotEmpty($state)) {
            $this->restore($state);
        }
    }

    public static function fromArray(array $state): self
    {
        return new self($state);
    }

    public function reset(): self
    {
        $this->_data = self::defaults();

        return $this;
    }

    public function restore(array $state): self
    {
        $this->_data = Arr::mergeRecursive(self::defaults(), $state);

        return $this;
    }

    public function setPersona(string $name = '', string $role = '', array $traits = []): self
    {
        $this->_data['persona'] = [
            'name' => self::text($name),
            'role' => self::text($role),
            'traits' => self::stringList($traits),
        ];

        return $this;
    }

    public function setTone(string $tone): self
    {
        $this->_data['tone'] = self::text($tone);

        return $this;
    }

    public function setMood(string $mood): self
    {
        $this->_data['mood'] = self::text($mood);

        return $this;
    }

    public function setTaskState(string $state): self
    {
        $this->_data['task']['state'] = self::text($state);

        return $this;
    }

    public function setSlot(string $name, mixed $value): self
    {
        $name = self::text($name);
        if ($name !== '') {
            $this->_data['task']['slots'][$name] = $value;
        }

        return $this;
    }

    public function slot(string $name, mixed $default = null): mixed
    {
        $name = self::text($name);

        return Arr::hasKey($this->_data['task']['slots'], $name)
            ? $this->_data['task']['slots'][$name]
            : $default;
    }

    public function setSession(?string $sessionId = null, ?string $userId = null, ?string $scope = null): self
    {
        $this->_data['session'] = [
            'id' => self::nullableText($sessionId),
            'user_id' => self::nullableText($userId),
            'scope' => self::nullableText($scope),
        ];

        return $this;
    }

    public function setMetadata(string $key, mixed $value): self
    {
        $key = self::text($key);
        if ($key !== '') {
            $this->_data['metadata'][$key] = $value;
        }

        return $this;
    }

    public function applyToContext(Context $context): void
    {
        $context->set('conversation_state', $this->toArray());
        $context->set('persona', $this->_data['persona']);
        $context->set('persona_name', $this->_data['persona']['name']);
        $context->set('persona_role', $this->_data['persona']['role']);
        $context->set('tone', $this->_data['tone']);
        $context->set('mood', $this->_data['mood']);
        $context->set('task_state', $this->_data['task']['state']);
        $context->set('task_slots', $this->_data['task']['slots']);
        $context->set('session_id', $this->_data['session']['id']);
        $context->set('user_id', $this->_data['session']['user_id']);
        $context->set('memory_scope', $this->_data['session']['scope']);
        $context->set('state_metadata', $this->_data['metadata']);
    }

    public function captureTurn(?Intent $intent, string $response): self
    {
        $this->_data['turn']['count'] = (int)($this->_data['turn']['count'] ?? 0) + 1;
        $this->_data['turn']['last_intent'] = $intent ? $intent->getLabel() : null;
        $this->_data['turn']['last_response'] = $response;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->_data;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function defaults(): array
    {
        return [
            'persona' => [
                'name' => '',
                'role' => '',
                'traits' => [],
            ],
            'tone' => '',
            'mood' => '',
            'task' => [
                'state' => 'idle',
                'slots' => [],
            ],
            'session' => [
                'id' => null,
                'user_id' => null,
                'scope' => null,
            ],
            'metadata' => [],
            'turn' => [
                'count' => 0,
                'last_intent' => null,
                'last_response' => null,
            ],
        ];
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    protected static function stringList(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            $value = self::text($value);
            if ($value !== '') {
                $list[] = $value;
            }
        }

        return $list;
    }

    protected static function nullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::text($value);
    }

    protected static function text(mixed $value): string
    {
        return Str::trim((string)$value);
    }
}
