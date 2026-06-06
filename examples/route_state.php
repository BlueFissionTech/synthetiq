<?php

declare(strict_types=1);

use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Training\RouteTrainer;
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

function parseRouteStateOptions(array $argv): array
{
    $options = [
        'state' => __DIR__ . '/../models/routes/synthetiq_routes.json',
        'model-dir' => __DIR__ . '/../models/ml/',
        'write' => false,
        'verify' => true,
        'apply' => false,
        'probe' => 'hello',
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            echo "Usage: php examples/route_state.php [--state=path] [--model-dir=path] [--write] [--no-verify] [--apply] [--probe=text]\n";
            exit(0);
        }

        if ($arg === '--write') {
            $options['write'] = true;
            continue;
        }
        if ($arg === '--apply') {
            $options['apply'] = true;
            continue;
        }
        if ($arg === '--no-verify') {
            $options['verify'] = false;
            continue;
        }
        if (str_starts_with($arg, '--state=')) {
            $options['state'] = substr($arg, 8);
            continue;
        }
        if (str_starts_with($arg, '--model-dir=')) {
            $options['model-dir'] = substr($arg, 12);
            continue;
        }
        if (str_starts_with($arg, '--probe=')) {
            $options['probe'] = substr($arg, 8);
            continue;
        }
    }

    return $options;
}

function routeStateSummary(array $summary, int $exitCode = 0): never
{
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($exitCode);
}

function compactRouteStateMeta(array $meta): array
{
    $extra = is_array($meta['extra'] ?? null) ? $meta['extra'] : [];

    return [
        'intents' => (int)($meta['intents'] ?? 0),
        'routes' => (int)($meta['routes'] ?? 0),
        'keywords' => (int)($meta['keywords'] ?? 0),
        'extra_keys' => array_keys($extra),
    ];
}

$options = parseRouteStateOptions($_SERVER['argv'] ?? $argv ?? []);

$dialogue = require __DIR__ . '/../sample_configs/dialogue.php';
$intentBoosts = require __DIR__ . '/../sample_configs/intent_boosts.php';
$grammar = require __DIR__ . '/../sample_configs/grammar.php';
$tokens = require __DIR__ . '/../sample_configs/tokens.php';
$documenter = require __DIR__ . '/../sample_configs/documenter.php';

$extra = [
    'grammar' => $grammar,
    'tokens' => $tokens,
];

$compiled = RouteTrainer::compile($dialogue, $intentBoosts, $extra);
$statePath = (string)$options['state'];
$wrote = false;
$loaded = $compiled;
$matches = null;

try {
    if ($options['write']) {
        RouteTrainer::saveState($compiled, $statePath);
        $wrote = true;
    }

    if (is_file($statePath)) {
        $loaded = RouteTrainer::loadState($statePath);
        $matches = RouteTrainer::stateMatches($loaded, $dialogue, $intentBoosts, $extra);
    } elseif ($options['verify']) {
        routeStateSummary([
            'state_path' => $statePath,
            'wrote' => $wrote,
            'verified' => false,
            'matches' => false,
            'error' => 'state file not found',
        ], 2);
    }
} catch (Throwable $e) {
    routeStateSummary([
        'state_path' => $statePath,
        'wrote' => $wrote,
        'verified' => false,
        'matches' => false,
        'error' => $e->getMessage(),
    ], 2);
}

if ($options['verify'] && $matches === false) {
    routeStateSummary([
        'state_path' => $statePath,
        'wrote' => $wrote,
        'verified' => true,
        'matches' => false,
        'compiled_cache_key' => $compiled['cache_key'],
        'state_cache_key' => $loaded['cache_key'] ?? null,
    ], 3);
}

$applySummary = null;
if ($options['apply']) {
    $modelDir = (string)$options['model-dir'];
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
    $ai = new SynthetIQ($interpreter, $analyzer, null, null, null, null, [
        'naive_bayes' => [
            'cache_dir' => $modelDir,
            'cache_key' => (string)$loaded['cache_key'],
        ],
    ]);

    $training = RouteTrainer::apply($ai, $loaded);
    $response = $ai->processInput((string)$options['probe']);

    $applySummary = [
        'training' => $training,
        'probe' => (string)$options['probe'],
        'response' => $response,
    ];
}

routeStateSummary([
    'state_path' => $statePath,
    'wrote' => $wrote,
    'verified' => (bool)$options['verify'],
    'matches' => $matches,
    'cache_key' => $compiled['cache_key'],
    'meta' => compactRouteStateMeta($compiled['meta'] ?? []),
    'applied' => $applySummary !== null,
    'apply' => $applySummary,
]);
