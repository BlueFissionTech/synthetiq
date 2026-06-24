<?php

declare(strict_types=1);

use BlueFission\Arr;

require __DIR__ . '/support.php';

$input = (string)($argv[1] ?? 'hello');
$runtime = synthetiq_example_build([
    'fallback' => true,
    'confidence_threshold' => 0.7,
]);

$ai = $runtime['ai'];
$envelope = $ai->processInputEnvelope($input);
$diagnostics = $ai->responsePredictorDiagnostics();

echo synthetiq_example_json([
    'input' => $envelope['input'] ?? ['raw' => $input],
    'response' => $envelope['response'] ?? '',
    'intent' => $envelope['intent'] ?? [],
    'fallback' => $envelope['fallback'] ?? [],
    'correction' => $envelope['correction'] ?? [],
    'predictor' => Arr::is($diagnostics) ? $diagnostics : [],
]) . PHP_EOL;
