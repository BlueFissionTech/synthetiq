<?php

namespace BlueFission\SynthetIQ\Intents;

interface IClassifier {
    public function classify(string $input): string;
}
