<?php

namespace BlueFission\SynthetIQ\Fallback;

use BlueFission\Automata\Context;

class NullFallbackResponder implements FallbackResponderInterface
{
    public function respond(string $input, Context $context, array $meta = []): ?string
    {
        return null;
    }
}
