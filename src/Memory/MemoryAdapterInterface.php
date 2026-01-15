<?php

namespace BlueFission\SynthetIQ\Memory;

use BlueFission\Automata\Context;

interface MemoryAdapterInterface
{
    public function recordExchange(string $input, string $response, Context $context, array $meta = []): void;

    public function recall(string $input, Context $context, array $meta = []): MemoryRecall;
}
