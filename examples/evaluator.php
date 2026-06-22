<?php

use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Intents\Classifier;
use BlueFission\SynthetIQ\Training\RouteTrainer;
use BlueFission\Cli\Args;
use BlueFission\Cli\Args\OptionDefinition;
use BlueFission\Cli\Util\ProgressBar;
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
use BlueFission\Arr;
use BlueFission\Data\FileSystem;
use BlueFission\Func;
use BlueFission\Num;
use BlueFission\Val;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../sample_configs/skills.php';

$dialogue = require __DIR__ . '/../sample_configs/dialogue.php';
$intentBoosts = require __DIR__ . '/../sample_configs/intent_boosts.php';
$grammar = require __DIR__ . '/../sample_configs/grammar.php';
$tokens = require __DIR__ . '/../sample_configs/tokens.php';
$documenter = require __DIR__ . '/../sample_configs/documenter.php';
$cases = require __DIR__ . '/../sample_configs/eval_cases.php';

function parseOptions(array $argv, array $defaults): array
{
    $options = $defaults;
    if (!class_exists(Args::class) || !class_exists(OptionDefinition::class)) {
        if (Val::is($argv[1] ?? null)) {
            $options['top'] = (int)$argv[1];
        }
        return $options;
    }

    $parser = new Args(['allowUnknown' => true, 'autoHelp' => true]);
    $parser->addOptions([
        new OptionDefinition('top', [
            'short' => ['k'],
            'type' => 'int',
            'default' => $defaults['top'],
            'description' => 'Number of top confusion entries to show.',
        ]),
        new OptionDefinition('model-dir', [
            'short' => ['m'],
            'type' => 'string',
            'default' => $defaults['model-dir'],
            'description' => 'Model cache directory.',
            'aliases' => ['model_dir'],
        ]),
        new OptionDefinition('train', [
            'short' => ['t'],
            'type' => 'bool',
            'default' => $defaults['train'],
            'description' => 'Enable training (use --no-train to skip).',
        ]),
        new OptionDefinition('progress', [
            'type' => 'bool',
            'default' => $defaults['progress'],
            'description' => 'Show training progress output.',
        ]),
    ]);

    $parser->parse($argv);
    $parsed = $parser->options();
    if (Val::isNotEmpty($parsed['help'] ?? null)) {
        $command = $argv[0] ?? 'evaluator.php';
        echo $parser->usage($command) . PHP_EOL;
        exit(0);
    }

    return array_merge($options, $parsed);
}

function buildProgressReporter(int $total, bool $enabled): callable
{
    if (!$enabled) {
        return function (): void {
        };
    }

    if (class_exists(ProgressBar::class)) {
        $bar = new ProgressBar($total);
        return function (int $current) use ($bar, $total): void {
            $line = $bar->render($current);
            echo "\r" . $line;
            if ($current >= $total) {
                echo PHP_EOL;
            }
        };
    }

    return function (int $current) use ($total): void {
        if ($current % 50 === 0 || $current >= $total) {
            $percent = (int)round(($current / $total) * 100);
            echo "\rTraining: {$current}/{$total} ({$percent}%)";
            if (Func::isCallable('flush')) {
                flush();
            }
            if ($current >= $total) {
                echo PHP_EOL;
            }
        }
    };
}

$defaults = [
    'model-dir' => __DIR__ . '/../models/ml/',
    'train' => true,
    'progress' => true,
    'top' => 3,
];
$options = parseOptions($_SERVER['argv'] ?? $argv ?? [], $defaults);

$modelDir = $options['model-dir'] ?? $defaults['model-dir'];
$files = new FileSystem();
$modelParent = dirname($modelDir);
if (!$files->exists($modelParent)) {
    $files->mkdir($modelParent);
}
if (!$files->exists($modelDir)) {
    $files->mkdir($modelDir);
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
$routerOptions = [
    'naive_bayes' => [
        'cache_dir' => $modelDir,
        'cache_key' => RouteTrainer::cacheKey($dialogue, $intentBoosts, [
            'grammar' => $grammar,
            'tokens' => $tokens,
        ]),
    ],
];
$ai = new SynthetIQ($interpreter, $analyzer, null, null, null, null, $routerOptions);
$classifier = new Classifier($analyzer);

if ($options['train']) {
    $reporter = buildProgressReporter(RouteTrainer::countRouteStatements($dialogue), (bool)$options['progress']);
    RouteTrainer::train($ai, $dialogue, $intentBoosts, static function (array $event) use ($reporter): void {
        if (($event['stage'] ?? null) === RouteTrainer::STAGE_ROUTE) {
            $reporter((int)$event['current']);
        }
    });
}

$total = Arr::count($cases);
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
    $topCount = (int)Num::max(1, (int)($options['top'] ?? 3));
    $topLabels = Arr::slice(Arr::keys($predictions), 0, $topCount);
    $top = [];
    foreach ($topLabels as $label) {
        $top[$label] = $predictions[$label];
    }
    $summary = [];
    foreach ($top as $label => $count) {
        $summary[] = "{$label}={$count}";
    }
    echo "{$expected}: " . implode(', ', $summary) . "\n";
}

exit;
