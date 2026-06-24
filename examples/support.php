<?php

declare(strict_types=1);

use BlueFission\Arr;
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Automata\Language\Documenter;
use BlueFission\Automata\Language\Grammar;
use BlueFission\Automata\Language\Interpreter;
use BlueFission\Automata\Language\StemmerLemmatizer;
use BlueFission\Automata\Language\Walker;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use BlueFission\Data\Directory;
use BlueFission\Data\File;
use BlueFission\Data\FileSystem;
use BlueFission\Func;
use BlueFission\Net\HTTP;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\SynthetIQ\Fallback\FallbackResponderInterface;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Training\RouteTrainer;
use BlueFission\Val;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$synthetiqExampleSkills = dirname(__DIR__) . '/sample_configs/skills.php';
if ((new File())->exists($synthetiqExampleSkills)) {
    require_once $synthetiqExampleSkills;
}

if (!class_exists(SynthetIQExampleFallbackResponder::class)) {
    final class SynthetIQExampleFallbackResponder implements FallbackResponderInterface
    {
        public function respond(string $input, Context $context, array $meta = []): ?string
        {
            $reason = (string)($meta['reason'] ?? 'fallback');

            return "I do not have a confident route for that yet. Reason: {$reason}.";
        }
    }
}

function synthetiq_example_root(): string
{
    return dirname(__DIR__);
}

function synthetiq_example_path(string $path = ''): string
{
    $path = Str::make($path)
        ->replace('/', DIRECTORY_SEPARATOR)
        ->replace('\\', DIRECTORY_SEPARATOR)
        ->val();

    return synthetiq_example_root() . ($path === '' ? '' : DIRECTORY_SEPARATOR . $path);
}

function synthetiq_example_config(): array
{
    return [
        'dialogue' => require synthetiq_example_path('sample_configs/dialogue.php'),
        'intent_boosts' => require synthetiq_example_path('sample_configs/intent_boosts.php'),
        'grammar' => require synthetiq_example_path('sample_configs/grammar.php'),
        'tokens' => require synthetiq_example_path('sample_configs/tokens.php'),
        'documenter' => require synthetiq_example_path('sample_configs/documenter.php'),
    ];
}

function synthetiq_example_directory(): Directory
{
    return new class(new FileSystem([
        'root' => synthetiq_example_root(),
        'filter' => [],
        'doNotConfirm' => true,
    ])) extends Directory {
    };
}

function synthetiq_example_ensure_directory(string $dir): void
{
    $directory = synthetiq_example_directory();
    if ($directory->exists($dir)) {
        return;
    }

    $parent = dirname($dir);
    if (!$directory->exists($parent)) {
        synthetiq_example_ensure_directory($parent);
    }

    $filesystem = new FileSystem([
        'root' => $parent,
        'filter' => [],
        'doNotConfirm' => true,
    ]);
    $filesystem->mkdir(basename($dir));
}

function synthetiq_example_model_dir(string $name = 'ml'): string
{
    $dir = synthetiq_example_path('models/' . $name);
    synthetiq_example_ensure_directory($dir);

    return $dir . DIRECTORY_SEPARATOR;
}

function synthetiq_example_interpreter(array $config): Interpreter
{
    $grammar = Arr::is($config['grammar'] ?? null) ? $config['grammar'] : [];
    $tokens = Arr::is($config['tokens'] ?? null) ? $config['tokens'] : [];
    $documenter = $config['documenter'] ?? new Documenter();

    return new Interpreter(
        new Grammar(
            new StemmerLemmatizer(),
            $grammar['rules'] ?? [],
            $grammar['commands'] ?? [],
            $tokens
        ),
        $documenter,
        new Walker()
    );
}

function synthetiq_example_router_options(array $config, string $modelDir): array
{
    $dialogue = Arr::is($config['dialogue'] ?? null) ? $config['dialogue'] : [];
    $intentBoosts = Arr::is($config['intent_boosts'] ?? null) ? $config['intent_boosts'] : [];

    return [
        'naive_bayes' => [
            'cache_dir' => $modelDir,
            'cache_key' => RouteTrainer::cacheKey($dialogue, $intentBoosts, [
                'grammar' => $config['grammar'] ?? [],
                'tokens' => $config['tokens'] ?? [],
            ]),
        ],
    ];
}

function synthetiq_example_create_ai(array $config, array $options = []): SynthetIQ
{
    $modelDir = (string)($options['model_dir'] ?? synthetiq_example_model_dir());
    $analyzer = new KeywordTopicAnalyzer(new NaiveBayesTextClassification(), $modelDir);
    $fallbackOption = $options['fallback'] ?? false;
    $fallback = $fallbackOption instanceof FallbackResponderInterface
        ? $fallbackOption
        : ($fallbackOption ? new SynthetIQExampleFallbackResponder() : null);
    $threshold = Val::is($options['confidence_threshold'] ?? null) ? (float)$options['confidence_threshold'] : null;
    $routerOptions = Arr::is($options['router_options'] ?? null)
        ? $options['router_options']
        : synthetiq_example_router_options($config, $modelDir);

    return new SynthetIQ(
        synthetiq_example_interpreter($config),
        $analyzer,
        null,
        null,
        $fallback,
        $threshold,
        $routerOptions
    );
}

function synthetiq_example_train(SynthetIQ $ai, array $config, ?callable $progress = null): array
{
    $dialogue = Arr::is($config['dialogue'] ?? null) ? $config['dialogue'] : [];
    $intentBoosts = Arr::is($config['intent_boosts'] ?? null) ? $config['intent_boosts'] : [];

    return RouteTrainer::train($ai, $dialogue, $intentBoosts, $progress);
}

function synthetiq_example_progress(bool $enabled = true): callable
{
    return static function (array $event) use ($enabled): void {
        if (!$enabled || ($event['stage'] ?? null) !== RouteTrainer::STAGE_ROUTE) {
            return;
        }

        $current = (int)($event['current'] ?? 0);
        $total = (int)($event['total'] ?? 0);
        if ($total <= 0 || ($current % 50 !== 0 && $current < $total)) {
            return;
        }

        $percent = (int)Num::round(($current / $total) * 100);
        echo "\rTraining: {$current}/{$total} ({$percent}%)";
        if (Func::isCallable('flush')) {
            flush();
        }
        if ($current >= $total) {
            echo PHP_EOL;
        }
    };
}

function synthetiq_example_build(array $options = []): array
{
    $config = synthetiq_example_config();
    $ai = synthetiq_example_create_ai($config, $options);
    $training = [];

    if (($options['train'] ?? true) !== false) {
        $training = synthetiq_example_train(
            $ai,
            $config,
            synthetiq_example_progress((bool)($options['progress'] ?? false))
        );
    }

    return [
        'ai' => $ai,
        'config' => $config,
        'training' => $training,
    ];
}

function synthetiq_example_json(array $payload): string
{
    $json = HTTP::jsonEncode($payload);

    return Str::is($json) ? $json : '{}';
}
