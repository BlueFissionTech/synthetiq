<?php

declare(strict_types=1);

use BlueFission\Arr;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Automata\Language\IInterpreter;
use BlueFission\Num;
use BlueFission\Security\Hash;
use BlueFission\Str;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\Val;

require __DIR__ . '/../vendor/autoload.php';

final class BenchmarkAnalyzer implements IAnalyzer
{
    protected array $labels;

    public function __construct(array $labels)
    {
        $this->labels = Arr::values($labels);
    }

    public function analyze(string $input, Context $context, array $keywords): Arr
    {
        $hash = Hash::value($input, 'crc32b');
        $index = (int)hexdec($hash) % (int)Num::max(1, Arr::count($this->labels));

        return new Arr([$this->labels[$index] => 1]);
    }
}

final class BenchmarkInterpreter implements IInterpreter
{
    public function load($file)
    {
        return null;
    }

    public function run($code)
    {
        return null;
    }

    public function isValid($code): bool
    {
        return true;
    }

    public function getTree(): array
    {
        return [];
    }

    public function tokenize(string $code): array
    {
        return Str::make($code)->trim()->splitBy('/\s+/')->toArray();
    }

    public function parse(array $tokens): array
    {
        return $tokens;
    }
}

function synthetiq_benchmark_argument(array $argv, int $index, int $default): int
{
    $value = $argv[$index] ?? $default;

    return Num::is($value) ? (int)$value : $default;
}

$iterations = (int)Num::max(1, synthetiq_benchmark_argument($argv ?? [], 1, 1000));
$intentCount = (int)Num::max(1, synthetiq_benchmark_argument($argv ?? [], 2, 50));
$templatesPerIntent = (int)Num::max(1, synthetiq_benchmark_argument($argv ?? [], 3, 5));

$labels = [];
for ($i = 0; $i < $intentCount; $i++) {
    $labels[] = "intent.{$i}";
}

$ai = new SynthetIQ(
    new BenchmarkInterpreter(),
    new BenchmarkAnalyzer($labels),
    null,
    null,
    null,
    null,
    ['enable_naive_bayes' => false]
);

foreach ($labels as $label) {
    for ($j = 0; $j < $templatesPerIntent; $j++) {
        $ai->addRoute("Template {$label} {$j}", $label, []);
    }
}

$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $ai->processInput("input {$i}");
}

$elapsed = microtime(true) - $start;
$perSecond = $elapsed > 0 ? $iterations / $elapsed : $iterations;
$elapsedText = (string)Num::make($elapsed)->precision(4);
$throughputText = (string)Num::make($perSecond)->precision(2);

echo "Iterations: {$iterations}\n";
echo "Intents: {$intentCount}\n";
echo "Templates per intent: {$templatesPerIntent}\n";
echo "Elapsed: {$elapsedText}s\n";
echo "Throughput: {$throughputText} inputs/s\n";
echo 'Predictor: ' . (string)($ai->responsePredictorDiagnostics()['status'] ?? 'unknown') . "\n";
