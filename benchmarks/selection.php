<?php

use BlueFission\Arr;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Automata\Language\IInterpreter;
use BlueFission\SynthetIQ\SynthetIQ;

require __DIR__ . '/../vendor/autoload.php';

class BenchmarkAnalyzer implements IAnalyzer
{
    protected $_labels;

    public function __construct(array $labels)
    {
        $this->_labels = array_values($labels);
    }

    public function analyze(string $input, Context $context, array $keywords): Arr
    {
        $index = crc32($input) % max(1, count($this->_labels));
        return new Arr([$this->_labels[$index] => 1]);
    }
}

class BenchmarkInterpreter implements IInterpreter
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
        return preg_split('/\s+/', trim($code)) ?: [];
    }

    public function parse(array $tokens): array
    {
        return $tokens;
    }
}

$iterations = (int)($argv[1] ?? 1000);
$intentCount = (int)($argv[2] ?? 50);
$templatesPerIntent = (int)($argv[3] ?? 5);

$labels = [];
for ($i = 0; $i < $intentCount; $i++) {
    $labels[] = "intent.$i";
}

$analyzer = new BenchmarkAnalyzer($labels);
$interpreter = new BenchmarkInterpreter();
$ai = new SynthetIQ($interpreter, $analyzer);

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

echo "Iterations: {$iterations}\n";
echo "Intents: {$intentCount}\n";
echo "Templates per intent: {$templatesPerIntent}\n";
echo "Elapsed: " . number_format($elapsed, 4) . "s\n";
echo "Throughput: " . number_format($perSecond, 2) . " inputs/s\n";
