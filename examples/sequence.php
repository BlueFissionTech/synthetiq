<?php

declare(strict_types=1);

use BlueFission\Str;
use BlueFission\Val;

require __DIR__ . '/support.php';

$runtime = synthetiq_example_build();
$ai = $runtime['ai'];

$fallbackResponse = "I'm not sure how to respond to that yet.";
$batches = [
    [
        'hello',
        'hi',
        'good morning',
        'how are you',
        "what's up",
        'thanks',
        'thank you',
        'goodbye',
        'see you later',
        "how's it going",
        'good day',
        'how have you been',
        'i am leaving',
        'how are things going',
        'bye',
    ],
    [
        'what time is it',
        'what is the date',
        'what is the weather today',
        'show me the news',
        'search for information',
        'create a list',
        'add item to list',
        'delete list',
        'how do i create a note',
        'schedule a meeting',
        'calculate the sum of x and y',
        'find file',
        'open file named x',
        'set a reminder',
        'set a timer',
    ],
    [
        'tell me a joke',
        'what is your favorite color',
        'do you have any hobbies',
        'where do you live',
        'can you cook',
        'who created you',
        'are you a robot',
        'what do you think about',
        'what are your dreams',
        'random input tokens',
        'blorf glorp',
        'sing me a song',
        'make me a sandwich',
        'explain quantum cats',
        'write code',
    ],
];

foreach ($batches as $index => $statements) {
    $batchNumber = $index + 1;
    echo "Batch {$batchNumber}\n";
    echo Str::make('-')->repeat(10)->val() . "\n";

    foreach ($statements as $statement) {
        $envelope = $ai->processInputEnvelope((string)$statement);
        $response = (string)($envelope['response'] ?? '');
        if (Val::isEmpty($response)) {
            $response = $fallbackResponse;
        }

        echo "You: {$statement}\n";
        echo "AI: {$response}\n";
        echo 'Intent: ' . (string)($envelope['intent']['label'] ?? 'unknown.intent') . "\n";
    }

    echo "\n";
}
