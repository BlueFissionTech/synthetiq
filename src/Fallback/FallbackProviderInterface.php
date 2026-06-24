<?php

namespace BlueFission\SynthetIQ\Fallback;

use BlueFission\Automata\Context;

interface FallbackProviderInterface
{
    public function complete(string $prompt, Context $context, array $meta = []): ?string;
}
