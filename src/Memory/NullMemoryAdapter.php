<?php

namespace BlueFission\SynthetIQ\Memory;

use BlueFission\Automata\Context;

class NullMemoryAdapter implements MemoryAdapterInterface
{
    public function recordExchange(string $input, string $response, Context $context, array $meta = []): void
    {
        return;
    }

    public function recall(string $input, Context $context, array $meta = []): MemoryRecall
    {
        return new MemoryRecall();
    }
}
