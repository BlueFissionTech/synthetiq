<?php

namespace BlueFission\SynthetIQ\Fallback;

use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;

class TrainingCandidateStore
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected array $candidates = [];
    protected int $nextId = 1;

    public function capture(array $candidate): array
    {
        $id = $candidate['id'] ?? 'candidate_' . $this->nextId;
        $this->nextId++;

        $record = Arr::merge($candidate, [
            'id' => $id,
            'status' => self::STATUS_PENDING,
        ]);

        $this->candidates[$id] = $record;

        return $record;
    }

    public function approve(string $id, array $meta = []): ?array
    {
        return $this->transition($id, self::STATUS_APPROVED, $meta);
    }

    public function reject(string $id, array $meta = []): ?array
    {
        return $this->transition($id, self::STATUS_REJECTED, $meta);
    }

    public function get(string $id): ?array
    {
        return Arr::hasKey($this->candidates, $id) ? $this->candidates[$id] : null;
    }

    public function all(): array
    {
        return Arr::values($this->candidates);
    }

    public function pending(): array
    {
        return $this->byStatus(self::STATUS_PENDING);
    }

    public function approved(): array
    {
        return $this->byStatus(self::STATUS_APPROVED);
    }

    public function rejected(): array
    {
        return $this->byStatus(self::STATUS_REJECTED);
    }

    protected function transition(string $id, string $status, array $meta): ?array
    {
        if (!Arr::hasKey($this->candidates, $id)) {
            return null;
        }

        $this->candidates[$id]['status'] = $status;
        if (Val::isNotEmpty($meta)) {
            $this->candidates[$id]['review'] = $meta;
        }

        return $this->candidates[$id];
    }

    protected function byStatus(string $status): array
    {
        return Arr::values(Arr::make($this->candidates)->filter(function ($candidate) use ($status): bool {
            return Arr::is($candidate) && Str::is($candidate['status'] ?? null) && $candidate['status'] === $status;
        })->toArray());
    }
}
