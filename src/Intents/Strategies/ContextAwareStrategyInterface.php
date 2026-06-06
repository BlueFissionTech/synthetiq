<?php

namespace BlueFission\SynthetIQ\Intents\Strategies;

use BlueFission\Automata\Context;

interface ContextAwareStrategyInterface
{
    public function setContext(Context $context): void;
}
