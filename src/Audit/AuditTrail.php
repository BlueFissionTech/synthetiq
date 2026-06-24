<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Audit;

use BlueFission\Arr;
use BlueFission\Func;

class AuditTrail
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $_records = [];

    protected $_redactor = null;

    public function setRedactor(?callable $redactor): void
    {
        $this->_redactor = $redactor;
    }

    public function record(string $event, array $payload = []): void
    {
        $this->_records[] = [
            'event' => $event,
            'payload' => $this->redact($payload),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function records(): array
    {
        return $this->_records;
    }

    public function clear(): void
    {
        $this->_records = [];
    }

    protected function redact(mixed $value): mixed
    {
        if (Arr::is($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                $redacted[$key] = $this->redact($item);
            }

            return $redacted;
        }

        if (Func::isCallable($this->_redactor)) {
            return ($this->_redactor)($value);
        }

        return $value;
    }
}
