<?php

namespace BlueFission\SynthetIQ\Fallback;

use BlueFission\Automata\Context;

interface FallbackResponderInterface
{
    public function respond(string $input, Context $context, array $meta = []): ?string;
}
