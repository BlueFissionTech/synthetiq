<?php

namespace BlueFission\SynthetIQ\Intents\Strategies;

use BlueFission\Automata\Context;
use BlueFission\Automata\Strategy\Strategy;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Collections\Collection;
use BlueFission\DevElation as Dev;

class KeywordOverlapStrategy extends Strategy implements ContextAwareStrategyInterface
{
    protected array $_keywordsByLabel = [];
    protected float $_accuracy = 0.0;

    public function setContext(Context $context): void
    {
        // Intentionally unused; keywords are context-agnostic.
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $samples = Dev::apply('synthetiq.intent.strategy.overlap.train.samples', $samples);
        $labels = Dev::apply('synthetiq.intent.strategy.overlap.train.labels', $labels);

        $this->_keywordsByLabel = $this->buildKeywordMap($samples, $labels);
        $this->_accuracy = $this->evaluateAccuracy($samples, $labels, $testSize);

        Dev::do('synthetiq.intent.strategy.overlap.trained', [
            'accuracy' => $this->_accuracy,
            'labels' => array_keys($this->_keywordsByLabel),
        ]);
    }

    public function predict($input)
    {
        $input = Dev::apply('synthetiq.intent.strategy.overlap.predict.input', $input);
        $tokens = $this->tokenize((string)$input);

        $scores = [];
        foreach ($this->_keywordsByLabel as $label => $keywords) {
            $shared = Arr::intersect($tokens, $keywords);
            $scores[$label] = count($shared);
        }

        if (!empty($scores)) {
            arsort($scores);
        }

        $scoresArr = Arr::make($scores);

        Dev::do('synthetiq.intent.strategy.overlap.predicted', [
            'scores' => $scoresArr->toArray(),
        ]);

        return $scoresArr;
    }

    public function accuracy(): float
    {
        return $this->_accuracy;
    }

    protected function evaluateAccuracy(array $samples, array $labels, float $testSize): float
    {
        $pairs = $this->buildTestPairs($samples, $labels, $testSize);
        if (empty($pairs)) {
            return 0.0;
        }

        $correct = 0;
        $total = 0;

        foreach ($pairs as $pair) {
            $label = $pair['label'] ?? null;
            if ($label === null) {
                continue;
            }

            $scores = $this->predict($pair['sample']);
            $predicted = $this->labelFromScores($scores);

            if ($predicted !== null && $predicted === $label) {
                $correct++;
            }
            $total++;
        }

        if ($total === 0) {
            return 0.0;
        }

        return $correct / $total;
    }

    protected function labelFromScores($scores): ?string
    {
        if ($scores instanceof Arr && $scores->count() > 0) {
            return $scores->keys()->get(0);
        }

        if (is_array($scores) && !empty($scores)) {
            $wrapped = Arr::make($scores);
            return $wrapped->keys()->get(0);
        }

        return null;
    }

    protected function buildKeywordMap(array $samples, array $labels): array
    {
        $map = [];
        $count = min(count($samples), count($labels));

        for ($i = 0; $i < $count; $i++) {
            $label = (string)$labels[$i];
            $sample = (string)$samples[$i];
            $tokens = $this->tokenize($sample);

            if (!isset($map[$label])) {
                $map[$label] = [];
            }

            $map[$label] = array_merge($map[$label], $tokens);
        }

        foreach ($map as $label => $keywords) {
            $map[$label] = Arr::unique($keywords);
        }

        return $map;
    }

    protected function tokenize(string $input): array
    {
        $input = Str::lower(Str::trim($input));
        if ($input === '') {
            return [];
        }

        $tokens = Str::split($input, ' ');
        $tokens = (new Collection($tokens))
            ->filter(function ($token) {
                return $token !== '';
            })
            ->toArray();

        return Arr::unique($tokens);
    }

    protected function buildTestPairs(array $samples, array $labels, float $testSize): array
    {
        $count = min(count($samples), count($labels));
        if ($count === 0) {
            return [];
        }

        $testCount = (int)round($count * $testSize);
        if ($testCount < 1) {
            $testCount = $count;
        } elseif ($testCount > $count) {
            $testCount = $count;
        }

        $start = $count - $testCount;
        $sampleSlice = Arr::slice($samples, $start);
        $labelSlice = Arr::slice($labels, $start);

        $pairs = [];
        $sliceCount = min(count($sampleSlice), count($labelSlice));
        for ($i = 0; $i < $sliceCount; $i++) {
            $pairs[] = [
                'sample' => $sampleSlice[$i],
                'label' => $labelSlice[$i],
            ];
        }

        return $pairs;
    }
}
