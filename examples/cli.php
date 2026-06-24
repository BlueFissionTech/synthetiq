<?php

declare(strict_types=1);

use BlueFission\Str;
use BlueFission\Val;

require __DIR__ . '/support.php';

$runtime = synthetiq_example_build(['progress' => true]);
$ai = $runtime['ai'];
$handle = fopen('php://stdin', 'r');

echo "SynthetIQ CLI (type 'exit' to quit)\n";
echo "----------------------------------\n";

while (true) {
    echo 'You: ';
    $userMessage = Str::make((string)fgets($handle))->trim()->val();

    if (Val::isEmpty($userMessage)) {
        continue;
    }

    if (Str::lower($userMessage) === 'exit') {
        echo "Goodbye!\n";
        break;
    }

    $envelope = $ai->processInputEnvelope($userMessage);
    echo 'AI: ' . (string)$envelope['response'] . PHP_EOL;
    echo 'Intent: ' . (string)($envelope['intent']['label'] ?? 'unknown.intent') . PHP_EOL;
}

fclose($handle);
