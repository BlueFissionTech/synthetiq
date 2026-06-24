<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Policy;

class PolicyDecision
{
    protected bool $_allowed;
    protected string $_reason;
    protected ?string $_replacement;

    /**
     * @var array<string, mixed>
     */
    protected array $_metadata;

    public function __construct(bool $allowed = true, string $reason = 'allowed', ?string $replacement = null, array $metadata = [])
    {
        $this->_allowed = $allowed;
        $this->_reason = $reason;
        $this->_replacement = $replacement;
        $this->_metadata = $metadata;
    }

    public static function allow(string $reason = 'allowed', array $metadata = []): self
    {
        return new self(true, $reason, null, $metadata);
    }

    public static function deny(string $reason, ?string $replacement = null, array $metadata = []): self
    {
        return new self(false, $reason, $replacement, $metadata);
    }

    public function allowed(): bool
    {
        return $this->_allowed;
    }

    public function reason(): string
    {
        return $this->_reason;
    }

    public function replacement(): ?string
    {
        return $this->_replacement;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->_metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->_allowed,
            'reason' => $this->_reason,
            'replacement' => $this->_replacement,
            'metadata' => $this->_metadata,
        ];
    }
}
