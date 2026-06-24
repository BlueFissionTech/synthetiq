<?php

declare(strict_types=1);

require __DIR__ . '/support.php';

$config = synthetiq_example_config();
$ai = synthetiq_example_create_ai($config, [
    'router_options' => [
        'enable_naive_bayes' => false,
    ],
]);

$routes = [
    'greeting.intent' => [
        'to' => ['greeting.reply'],
        'statements' => ['hello', 'hi there', 'good morning'],
    ],
    'greeting.reply' => [
        'to' => [],
        'statements' => ['Hello!', 'Hi there!', 'Good to see you.'],
    ],
];

foreach ($routes as $type => $route) {
    foreach ($route['statements'] as $statement) {
        $ai->addRoute((string)$statement, (string)$type, $route['to']);
    }
}

$input = (string)($argv[1] ?? 'hello');
$envelope = $ai->processInputEnvelope($input);

echo "You: {$input}\n";
echo 'AI: ' . (string)$envelope['response'] . "\n";
echo 'Intent: ' . (string)($envelope['intent']['label'] ?? 'unknown.intent') . "\n";
