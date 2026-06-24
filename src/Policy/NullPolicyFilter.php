<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Policy;

use BlueFission\Automata\Context;

class NullPolicyFilter implements PolicyFilterInterface
{
    public function inspectInput(string $input, Context $context, array $meta = []): PolicyDecision
    {
        return PolicyDecision::allow('input_allowed');
    }

    public function inspectOutput(string $output, Context $context, array $meta = []): PolicyDecision
    {
        return PolicyDecision::allow('output_allowed');
    }
}
