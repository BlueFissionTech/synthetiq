<?php

namespace BlueFission\SynthetIQ\Responses;

use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Context;



interface IGenerator {
    public function generate(string $input, Intent $intent, Context $context): string;
}
