<?php

namespace BlueFission\SynthetIQ\Responses;

interface IGenerator {
    public function generate(string $input, string $intent, array $context): string;
}
