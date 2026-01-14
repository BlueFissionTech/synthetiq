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
require __DIR__ . '/../sample_configs/skills.php';

$dialogue = require __DIR__ . '/../sample_configs/dialogue.php';
$intentBoosts = require __DIR__ . '/../sample_configs/intent_boosts.php';
$grammar = require __DIR__ . '/../sample_configs/grammar.php';
$tokens = require __DIR__ . '/../sample_configs/tokens.php';
$documenter = require __DIR__ . '/../sample_configs/documenter.php';
$statements = require __DIR__ . '/../sample_configs/statements.php';

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

function normalizeKeywords(array $keywords, array $exclude = []): array
{
    $excludeSet = [];
    foreach ($exclude as $value) {
        $excludeSet[strtolower(trim((string)$value))] = true;
    }

    $normalized = [];
    foreach ($keywords as $keyword) {
        $keyword = strtolower(trim((string)$keyword));
        if ($keyword === '' || isset($excludeSet[$keyword])) {
            continue;
        }
        $normalized[$keyword] = true;
    }

    return array_keys($normalized);
}

function trainRoutes(SynthetIQ $ai, array $dialogue, array $intentBoosts = []): void
{
    $stopwords = ['how', 'what', 'is', 'the', 'a', 'an', 'to', 'for', 'on', 'in'];
    $total = 0;
    foreach ($dialogue as $info) {
        $total += count($info[1]);
    }

    $current = 0;
    foreach ($dialogue as $category => $info) {
        $boost = $intentBoosts[$category] ?? [];
        $keywords = $info[2] ?? [];
        if (!empty($boost['keywords'])) {
            $keywords = array_merge($keywords, $boost['keywords']);
        }
        $exclude = $boost['exclude'] ?? [];
        $keywords = normalizeKeywords($keywords, array_merge($stopwords, $exclude));
        $priorityBase = $boost['priority'] ?? null;
        $ai->addIntentKeywords($category, $keywords, $priorityBase);

        foreach ($info[1] as $statement) {
            $ai->addRoute($statement, $category, $info[0]);
            $current++;
            if ($current % 50 === 0 || $current === $total) {
                $percent = (int)round(($current / $total) * 100);
                echo "\rTraining: {$current}/{$total} ({$percent}%)";
                if (function_exists('flush')) {
                    flush();
                }
            }
        }
    }
    echo "\rTraining: {$total}/{$total} (100%)\n";
}

trainRoutes($ai, $dialogue, $intentBoosts);

$iterations = (int)($argv[1] ?? count($statements));
$iterations = max(1, $iterations);

for ($i = 0; $i < $iterations; $i++) {
    $statement = $statements[$i % count($statements)];
    $response = $ai->processInput($statement);
    if ($response === '') {
        $response = "I'm not sure how to respond to that yet.";
    }

    echo "You: {$statement}\n";
    echo "AI: {$response}\n";
}
