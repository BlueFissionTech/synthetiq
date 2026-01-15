<?php

namespace BlueFission\SynthetIQ\Evaluation;

use BlueFission\Automata\Context;
use BlueFission\SynthetIQ\Intents\IClassifier;

class IntentEvaluator
{
    protected $normalizer;

    public function __construct(?callable $normalizer = null)
    {
        $this->normalizer = $normalizer;
    }

    public function evaluate(IClassifier $classifier, array $cases): array
    {
        $total = 0;
        $correct = 0;
        $confusion = [];

        foreach ($cases as $case) {
            $input = $case['input'] ?? '';
            $expected = $case['intent'] ?? 'unknown.intent';

            if (!is_string($input)) {
                continue;
            }

            $normalized = $this->normalize($input);
            $context = new Context();
            $intent = $classifier->classify($normalized, $context);
            $predicted = $intent ? $intent->getLabel() : 'unknown.intent';

            if (!isset($confusion[$expected])) {
                $confusion[$expected] = [];
            }
            $confusion[$expected][$predicted] = ($confusion[$expected][$predicted] ?? 0) + 1;

            if ($predicted === $expected) {
                $correct++;
            }

            $total++;
        }

        $accuracy = $total > 0 ? ($correct / $total) : 0.0;

        return [
            'total' => $total,
            'correct' => $correct,
            'accuracy' => $accuracy,
            'confusion' => $confusion,
        ];
    }

    protected function normalize(string $input): string
    {
        if ($this->normalizer) {
            return (string)call_user_func($this->normalizer, $input);
        }

        return $input;
    }
}
