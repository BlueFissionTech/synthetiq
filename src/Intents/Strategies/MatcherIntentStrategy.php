<?php

namespace BlueFission\SynthetIQ\Intents\Strategies;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Matcher;
use BlueFission\Automata\Strategy\Strategy;
use BlueFission\Arr;
use BlueFission\DevElation as Dev;

class MatcherIntentStrategy extends Strategy implements ContextAwareStrategyInterface
{
    protected Matcher $_matcher;
    protected Context $_context;
    protected float $_accuracy = 0.0;
    protected ?Arr $_lastScores = null;

    public function __construct(Matcher $matcher, ?Context $context = null)
    {
        $this->_matcher = $matcher;
        $this->_context = $context ?? new Context();
    }

    public function setContext(Context $context): void
    {
        $this->_context = $context;
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $samples = Dev::apply('synthetiq.intent.strategy.matcher.train.samples', $samples);
        $labels = Dev::apply('synthetiq.intent.strategy.matcher.train.labels', $labels);

        $this->_accuracy = $this->evaluateAccuracy($samples, $labels, $testSize);

        Dev::do('synthetiq.intent.strategy.matcher.trained', [
            'accuracy' => $this->_accuracy,
        ]);
    }

    public function predict($input)
    {
        $input = Dev::apply('synthetiq.intent.strategy.matcher.predict.input', $input);

        try {
            $scores = $this->_matcher->match((string)$input, $this->_context);
        } catch (\Throwable $e) {
            $scores = Arr::make();
        }

        $this->_lastScores = $scores instanceof Arr ? $scores : Arr::make();

        Dev::do('synthetiq.intent.strategy.matcher.predicted', [
            'scores' => $this->_lastScores->toArray(),
        ]);

        return $this->_lastScores;
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
