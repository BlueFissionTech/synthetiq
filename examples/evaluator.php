<?php

use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Intents\Classifier;
use BlueFission\Automata\Context;
use BlueFission\Automata\Language\{
    Interpreter,
    Grammar,
    StemmerLemmatizer,
    Documenter,
    Walker
};
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use BlueFission\Automata\Language\ContractionNormalizer;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../sample_configs/skills.php';

$dialogue = require __DIR__ . '/../sample_configs/dialogue.php';
$intentBoosts = require __DIR__ . '/../sample_configs/intent_boosts.php';
$grammar = require __DIR__ . '/../sample_configs/grammar.php';
$tokens = require __DIR__ . '/../sample_configs/tokens.php';
$documenter = require __DIR__ . '/../sample_configs/documenter.php';
$cases = require __DIR__ . '/../sample_configs/eval_cases.php';

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
$classifier = new Classifier($analyzer);

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

$total = count($cases);
$correct = 0;
$confusion = [];

foreach ($cases as $case) {
    $context = new Context();
    $input = ContractionNormalizer::normalize($case['input']);
    $expected = $case['intent'];

    $intent = $classifier->classify($input, $context);
    $predicted = $intent ? $intent->getLabel() : 'unknown.intent';

    if (!isset($confusion[$expected])) {
        $confusion[$expected] = [];
    }
    $confusion[$expected][$predicted] = ($confusion[$expected][$predicted] ?? 0) + 1;

    if ($predicted === $expected) {
        $correct++;
    }
}

$accuracy = $total > 0 ? ($correct / $total) * 100 : 0;

echo "Cases: {$total}\n";
echo "Correct: {$correct}\n";
echo "Accuracy: " . number_format($accuracy, 2) . "%\n\n";

foreach ($confusion as $expected => $predictions) {
    arsort($predictions);
    $top = array_slice($predictions, 0, 3, true);
    $summary = [];
    foreach ($top as $label => $count) {
        $summary[] = "{$label}={$count}";
    }
    echo "{$expected}: " . implode(', ', $summary) . "\n";
}

exit;
