<?php

declare(strict_types=1);

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Language\ContractionNormalizer;
use BlueFission\Cli\Args;
use BlueFission\Cli\Args\OptionDefinition;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\SynthetIQ\Evaluation\IntentEvaluator;
use BlueFission\SynthetIQ\Intents\IClassifier;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\Val;

require __DIR__ . '/support.php';

if (!class_exists(SynthetIQEnvelopeClassifier::class)) {
    final class SynthetIQEnvelopeClassifier implements IClassifier
    {
        public function __construct(private SynthetIQ $ai)
        {
        }

        public function classify(string $input, Context $context): ?Intent
        {
            $envelope = $this->ai->processInputEnvelope($input);
            $label = (string)($envelope['intent']['label'] ?? '');

            return Val::isEmpty($label) ? null : new Intent($label, $label);
        }
    }
}

function synthetiq_evaluator_options(array $argv): array
{
    $defaults = [
        'top' => 3,
        'train' => true,
        'progress' => true,
    ];

    if (!class_exists(Args::class) || !class_exists(OptionDefinition::class)) {
        if (Val::is($argv[1] ?? null) && Num::is($argv[1])) {
            $defaults['top'] = (int)$argv[1];
        }

        return $defaults;
    }

    $parser = new Args(['allowUnknown' => true, 'autoHelp' => true]);
    $parser->addOptions([
        new OptionDefinition('top', [
            'short' => ['k'],
            'type' => 'int',
            'default' => $defaults['top'],
            'description' => 'Number of top confusion entries to show.',
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
            'description' => 'Show route training progress output.',
        ]),
    ]);

    $parser->parse($argv);
    $parsed = $parser->options();
    if (Val::isNotEmpty($parsed['help'] ?? null)) {
        echo $parser->usage((string)($argv[0] ?? 'evaluator.php')) . PHP_EOL;
        exit(0);
    }

    return Arr::merge($defaults, $parsed);
}

function synthetiq_evaluator_line(array $counts, int $limit): string
{
    arsort($counts);
    $labels = Arr::slice(Arr::keys($counts), 0, (int)Num::max(1, $limit));
    $line = '';

    foreach ($labels as $label) {
        $segment = "{$label}={$counts[$label]}";
        $line = Val::isEmpty($line)
            ? $segment
            : Str::make($line)->append(', ')->append($segment)->val();
    }

    return $line;
}

$options = synthetiq_evaluator_options($_SERVER['argv'] ?? $argv ?? []);
$runtime = synthetiq_example_build([
    'train' => (bool)($options['train'] ?? true),
    'progress' => (bool)($options['progress'] ?? true),
]);

$cases = require synthetiq_example_path('sample_configs/eval_cases.php');
$evaluator = new IntentEvaluator(static fn(string $input): string => ContractionNormalizer::normalize($input));
$result = $evaluator->evaluate(new SynthetIQEnvelopeClassifier($runtime['ai']), $cases);
$accuracy = (float)$result['accuracy'] * 100;
$accuracyText = (string)Num::make($accuracy)->precision(2);

echo 'Cases: ' . (string)$result['total'] . "\n";
echo 'Correct: ' . (string)$result['correct'] . "\n";
echo "Accuracy: {$accuracyText}%\n\n";

$confusion = Arr::is($result['confusion'] ?? null) ? $result['confusion'] : [];
foreach ($confusion as $expected => $predictions) {
    $predictions = Arr::is($predictions) ? $predictions : [];
    echo "{$expected}: " . synthetiq_evaluator_line($predictions, (int)($options['top'] ?? 3)) . "\n";
}
