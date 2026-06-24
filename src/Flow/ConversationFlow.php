<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Flow;

use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;

class ConversationFlow
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_ABANDONED = 'abandoned';

    /**
     * @var array<string, mixed>
     */
    protected array $_definition = [];

    protected string $_currentState = '';
    protected string $_status = self::STATUS_ACTIVE;

    /**
     * @var array<string, mixed>
     */
    protected array $_lastTransition = [];

    public function __construct(array $definition)
    {
        $this->_definition = self::normalize($definition);
        $this->reset();
    }

    public static function fromArray(array $definition): self
    {
        return new self($definition);
    }

    /**
     * @return array<int, string>
     */
    public static function validate(array $definition): array
    {
        $errors = [];
        $states = Arr::is($definition['states'] ?? null) ? $definition['states'] : [];
        if (Val::isEmpty($states)) {
            return ['Flow states are required.'];
        }

        $start = self::text($definition['start'] ?? '');
        if (Val::isEmpty($start)) {
            $errors[] = 'Flow start state is required.';
        } elseif (!Arr::hasKey($states, $start)) {
            $errors[] = "Flow start state {$start} is not defined.";
        }

        foreach ($states as $id => $state) {
            $stateId = self::text($id);
            if (!Arr::is($state)) {
                $errors[] = "Flow state {$stateId} must be an array.";
                continue;
            }

            $errors = Arr::merge($errors, self::validateState($stateId, $state, $states));
        }

        return $errors;
    }

    public function reset(): self
    {
        $this->_currentState = (string)($this->_definition['start'] ?? '');
        $this->_status = self::STATUS_ACTIVE;
        $this->_lastTransition = [];

        return $this;
    }

    public function abandon(): self
    {
        $this->_status = self::STATUS_ABANDONED;

        return $this;
    }

    public function complete(): self
    {
        $this->_status = self::STATUS_COMPLETE;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->_status === self::STATUS_ACTIVE;
    }

    public function isComplete(): bool
    {
        return $this->_status === self::STATUS_COMPLETE;
    }

    public function isAbandoned(): bool
    {
        return $this->_status === self::STATUS_ABANDONED;
    }

    public function currentStateId(): string
    {
        return $this->_currentState;
    }

    /**
     * @return array<string, mixed>
     */
    public function currentState(): array
    {
        $states = Arr::is($this->_definition['states'] ?? null) ? $this->_definition['states'] : [];

        return Arr::is($states[$this->_currentState] ?? null) ? $states[$this->_currentState] : [];
    }

    /**
     * @return array<int, string>
     */
    public function allowedIntents(): array
    {
        $state = $this->currentState();

        return self::stringList(Arr::is($state['allowed_intents'] ?? null) ? $state['allowed_intents'] : []);
    }

    public function fallbackIntent(): ?string
    {
        $state = $this->currentState();
        $intent = self::text($state['fallback_intent'] ?? '');

        return Val::isEmpty($intent) ? null : $intent;
    }

    public function constrainScores(?Arr $scores): ?Arr
    {
        if (!$this->isActive()) {
            return $scores;
        }

        $allowed = $this->allowedIntents();
        if (Val::isEmpty($allowed) || !$scores instanceof Arr) {
            return $scores;
        }

        $filtered = [];
        $scoreData = $scores->toArray();
        foreach ($allowed as $intent) {
            if (Arr::hasKey($scoreData, $intent)) {
                $filtered[$intent] = $scoreData[$intent];
            }
        }

        if (Val::isNotEmpty($filtered)) {
            arsort($filtered);

            return Arr::make($filtered);
        }

        $fallbackIntent = $this->fallbackIntent();
        if ($fallbackIntent !== null) {
            return Arr::make([$fallbackIntent => 1.0]);
        }

        return $scores;
    }

    public function advance(?string $intentLabel): self
    {
        if (!$this->isActive()) {
            return $this;
        }

        $state = $this->currentState();
        $intentLabel = self::text($intentLabel ?? '');
        $transitions = Arr::is($state['transitions'] ?? null) ? $state['transitions'] : [];
        $target = Arr::hasKey($transitions, $intentLabel)
            ? self::text($transitions[$intentLabel])
            : self::text($state['fallback'] ?? '');

        $this->_lastTransition = [
            'from' => $this->_currentState,
            'intent' => $intentLabel,
            'to' => $target,
            'fallback' => !Arr::hasKey($transitions, $intentLabel),
        ];

        if (Val::isEmpty($target)) {
            return $this;
        }

        $this->_currentState = $target;
        if ($this->stateCompletes($this->currentState())) {
            $this->complete();
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        return [
            'active' => $this->isActive(),
            'status' => $this->_status,
            'current_state' => $this->_currentState,
            'allowed_intents' => $this->allowedIntents(),
            'fallback_intent' => $this->fallbackIntent(),
            'last_transition' => $this->_lastTransition,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'definition' => $this->_definition,
            'current_state' => $this->_currentState,
            'status' => $this->_status,
            'last_transition' => $this->_lastTransition,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function normalize(array $definition): array
    {
        $states = [];
        foreach ((Arr::is($definition['states'] ?? null) ? $definition['states'] : []) as $id => $state) {
            if (Arr::is($state)) {
                $states[self::text($id)] = $state;
            }
        }

        return [
            'id' => self::text($definition['id'] ?? ''),
            'start' => self::text($definition['start'] ?? ''),
            'states' => $states,
        ];
    }

    /**
     * @param array<string, mixed> $states
     * @return array<int, string>
     */
    protected static function validateState(string $stateId, array $state, array $states): array
    {
        $errors = [];
        $transitions = Arr::is($state['transitions'] ?? null) ? $state['transitions'] : [];
        foreach ($transitions as $intent => $target) {
            $target = self::text($target);
            if (Val::isNotEmpty($target) && !Arr::hasKey($states, $target)) {
                $errors[] = "Flow state {$stateId} transition {$intent} points to missing state {$target}.";
            }
        }

        $fallback = self::text($state['fallback'] ?? '');
        if (Val::isNotEmpty($fallback) && !Arr::hasKey($states, $fallback)) {
            $errors[] = "Flow state {$stateId} fallback points to missing state {$fallback}.";
        }

        return $errors;
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
            if (Val::isNotEmpty($value)) {
                $list[] = $value;
            }
        }

        return $list;
    }

    protected function stateCompletes(array $state): bool
    {
        return (bool)($state['complete'] ?? false);
    }

    protected static function text(mixed $value): string
    {
        return Str::trim((string)$value);
    }
}
