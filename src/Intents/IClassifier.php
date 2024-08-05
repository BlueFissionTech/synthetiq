<?php

namespace BlueFission\SynthetIQ\Intents;

use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Context;

interface IClassifier {
    public function classify(string $input, Context $context): ?Intent;
}
