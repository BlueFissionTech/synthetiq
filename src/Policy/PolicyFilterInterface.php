<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Policy;

use BlueFission\Automata\Context;

interface PolicyFilterInterface
{
    public function inspectInput(string $input, Context $context, array $meta = []): PolicyDecision;

    public function inspectOutput(string $output, Context $context, array $meta = []): PolicyDecision;
}
