<?php

namespace BlueFission\SynthetIQ\Memory;

class MemoryRecall
{
    protected array $related;
    protected array $intentBiases;
    protected array $meta;

    public function __construct(array $related = [], array $intentBiases = [], array $meta = [])
    {
        $this->related = $related;
        $this->intentBiases = $intentBiases;
        $this->meta = $meta;
    }

    public function related(): array
    {
        return $this->related;
    }

    public function intentBiases(): array
    {
        return $this->intentBiases;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function isEmpty(): bool
    {
        return empty($this->related) && empty($this->intentBiases);
    }

    public function toArray(): array
    {
        return [
            'related' => $this->related,
            'intentBiases' => $this->intentBiases,
            'meta' => $this->meta,
        ];
    }
}
