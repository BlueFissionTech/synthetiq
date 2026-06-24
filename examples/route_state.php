<?php

declare(strict_types=1);

use BlueFission\Arr;
use BlueFission\Data\File;
use BlueFission\Str;
use BlueFission\SynthetIQ\Training\RouteTrainer;
use BlueFission\Val;

require __DIR__ . '/support.php';

function synthetiq_route_state_options(array $argv): array
{
    $options = [
        'state' => synthetiq_example_path('models/routes/synthetiq_routes.json'),
        'model-dir' => synthetiq_example_model_dir(),
        'write' => false,
        'verify' => true,
        'apply' => false,
        'probe' => 'hello',
    ];

    foreach (Arr::slice($argv, 1) as $arg) {
        $arg = (string)$arg;
        if (Arr::has(['--help', '-h'], $arg, true)) {
            echo "Usage: php examples/route_state.php [--state=path] [--model-dir=path] [--write] [--no-verify] [--apply] [--probe=text]\n";
            exit(0);
        }

        if (Str::match($arg, '--write')) {
            $options['write'] = true;
            continue;
        }

        if (Str::match($arg, '--apply')) {
            $options['apply'] = true;
            continue;
        }

        if (Str::match($arg, '--no-verify')) {
            $options['verify'] = false;
            continue;
        }

        if (Str::startsWith($arg, '--state=')) {
            $options['state'] = Str::sub($arg, 8);
            continue;
        }

        if (Str::startsWith($arg, '--model-dir=')) {
            $options['model-dir'] = Str::sub($arg, 12);
            continue;
        }

        if (Str::startsWith($arg, '--probe=')) {
            $options['probe'] = Str::sub($arg, 8);
        }
    }

    return $options;
}

function synthetiq_route_state_summary(array $summary, int $exitCode = 0): never
{
    echo synthetiq_example_json($summary) . PHP_EOL;
    exit($exitCode);
}

function synthetiq_route_state_meta(array $meta): array
{
    $extra = Arr::is($meta['extra'] ?? null) ? $meta['extra'] : [];

    return [
        'intents' => (int)($meta['intents'] ?? 0),
        'routes' => (int)($meta['routes'] ?? 0),
        'keywords' => (int)($meta['keywords'] ?? 0),
        'extra_keys' => Arr::keys($extra),
    ];
}

$options = synthetiq_route_state_options($_SERVER['argv'] ?? $argv ?? []);
$config = synthetiq_example_config();
$dialogue = Arr::is($config['dialogue'] ?? null) ? $config['dialogue'] : [];
$intentBoosts = Arr::is($config['intent_boosts'] ?? null) ? $config['intent_boosts'] : [];
$extra = [
    'grammar' => $config['grammar'] ?? [],
    'tokens' => $config['tokens'] ?? [],
];

$compiled = RouteTrainer::compile($dialogue, $intentBoosts, $extra);
$statePath = (string)$options['state'];
$wrote = false;
$loaded = $compiled;
$matches = null;

try {
    if ((bool)$options['write']) {
        RouteTrainer::saveState($compiled, $statePath);
        $wrote = true;
    }

    if ((new File())->exists($statePath)) {
        $loaded = RouteTrainer::loadState($statePath);
        $matches = RouteTrainer::stateMatches($loaded, $dialogue, $intentBoosts, $extra);
    } elseif ((bool)$options['verify']) {
        synthetiq_route_state_summary([
            'state_path' => $statePath,
            'wrote' => $wrote,
            'verified' => false,
            'matches' => false,
            'error' => 'state file not found',
        ], 2);
    }
} catch (Throwable $e) {
    synthetiq_route_state_summary([
        'state_path' => $statePath,
        'wrote' => $wrote,
        'verified' => false,
        'matches' => false,
        'error' => $e->getMessage(),
    ], 2);
}

if ((bool)$options['verify'] && $matches === false) {
    synthetiq_route_state_summary([
        'state_path' => $statePath,
        'wrote' => $wrote,
        'verified' => true,
        'matches' => false,
        'compiled_cache_key' => $compiled['cache_key'],
        'state_cache_key' => $loaded['cache_key'] ?? null,
    ], 3);
}

$applySummary = null;
if ((bool)$options['apply']) {
    $modelDir = (string)$options['model-dir'];
    $ai = synthetiq_example_create_ai($config, [
        'model_dir' => $modelDir,
        'router_options' => [
            'naive_bayes' => [
                'cache_dir' => $modelDir,
                'cache_key' => (string)$loaded['cache_key'],
            ],
        ],
    ]);
    $training = RouteTrainer::apply($ai, $loaded);
    $envelope = $ai->processInputEnvelope((string)$options['probe']);

    $applySummary = [
        'training' => $training,
        'probe' => (string)$options['probe'],
        'response' => (string)($envelope['response'] ?? ''),
        'intent' => (string)($envelope['intent']['label'] ?? 'unknown.intent'),
    ];
}

synthetiq_route_state_summary([
    'state_path' => $statePath,
    'wrote' => $wrote,
    'verified' => (bool)$options['verify'],
    'matches' => $matches,
    'cache_key' => $compiled['cache_key'],
    'meta' => synthetiq_route_state_meta($compiled['meta'] ?? []),
    'applied' => $applySummary !== null,
    'apply' => $applySummary,
]);
