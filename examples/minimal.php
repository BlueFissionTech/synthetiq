<?php

use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\Automata\Language\{
    Interpreter,
    Grammar,
    StemmerLemmatizer,
    Documenter,
    Walker
};
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;

require __DIR__ . '/../vendor/autoload.php';

$grammar = require __DIR__ . '/../sample_configs/grammar.php';
$tokens = require __DIR__ . '/../sample_configs/tokens.php';
$documenter = require __DIR__ . '/../sample_configs/documenter.php';

$modelDir = __DIR__ . '/../models/ml/';
if (!is_dir($modelDir)) {
    mkdir($modelDir, 0777, true);
}

$interpreter = new Interpreter(
    new Grammar(
        new StemmerLemmatizer(),
        $grammar['rules'],
        $grammar['commands'],
        $tokens
    ),
    $documenter,
    new Walker()
);

$analyzer = new KeywordTopicAnalyzer(new NaiveBayesTextClassification, $modelDir);
$ai = new SynthetIQ($interpreter, $analyzer);

$routes = [
    'greeting.intent' => [
        'to' => ['greeting.reply'],
        'statements' => [
            'hello',
            'hi there',
            'good morning',
        ],
    ],
    'greeting.reply' => [
        'to' => [],
        'statements' => [
            'Hello!',
            'Hi there!',
            'Good to see you.',
        ],
    ],
];

foreach ($routes as $type => $route) {
    foreach ($route['statements'] as $statement) {
        $ai->addRoute($statement, $type, $route['to']);
    }
}

$input = $argv[1] ?? 'hello';
$response = $ai->processInput($input);

echo "You: {$input}\n";
echo "AI: {$response}\n";
