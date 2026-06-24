<?php

declare(strict_types=1);

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Val;

require __DIR__ . '/support.php';

$runtime = synthetiq_example_build();
$ai = $runtime['ai'];
$statements = require synthetiq_example_path('sample_configs/statements.php');
$statements = Arr::is($statements) ? $statements : [];
$iterations = (int)Num::max(1, (int)($argv[1] ?? Arr::count($statements)));
$statementCount = Arr::count($statements);

for ($i = 0; $i < $iterations; $i++) {
    if ($statementCount === 0) {
        echo "No sample statements configured.\n";
        break;
    }

    $statement = (string)$statements[$i % $statementCount];
    $envelope = $ai->processInputEnvelope($statement);
    $response = (string)($envelope['response'] ?? '');

    if (Val::isEmpty($response)) {
        $response = "I'm not sure how to respond to that yet.";
    }

    echo "You: {$statement}\n";
    echo "AI: {$response}\n";
    echo 'Intent: ' . (string)($envelope['intent']['label'] ?? 'unknown.intent') . "\n";
}
