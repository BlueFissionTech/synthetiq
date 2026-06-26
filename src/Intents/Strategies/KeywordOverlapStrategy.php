<?php

namespace BlueFission\SynthetIQ\Intents\Strategies;

use BlueFission\Automata\Context;
use BlueFission\Automata\Strategy\Strategy;
use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Collections\Collection;
use BlueFission\DevElation as Dev;

class KeywordOverlapStrategy extends Strategy implements ContextAwareStrategyInterface
{
    protected const STOPWORDS = [
        'a' => true,
        'an' => true,
        'and' => true,
        'are' => true,
        'can' => true,
        'do' => true,
        'for' => true,
        'i' => true,
        'in' => true,
        'is' => true,
        'it' => true,
        'me' => true,
        'of' => true,
        'on' => true,
        'the' => true,
        'to' => true,
        'what' => true,
        'you' => true,
        'your' => true,
    ];

    protected array $_keywordsByLabel = [];
    protected array $_phrasesByLabel = [];
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
        $this->_phrasesByLabel = $this->buildPhraseMap($samples, $labels);
        $this->_accuracy = $this->evaluateAccuracy($samples, $labels, $testSize);

        Dev::do('synthetiq.intent.strategy.overlap.trained', [
            'accuracy' => $this->_accuracy,
            'labels' => Arr::keys($this->_keywordsByLabel),
        ]);
    }

    public function predict($input)
    {
        $input = Dev::apply('synthetiq.intent.strategy.overlap.predict.input', $input);
        $normalizedInput = $this->normalizeText((string)$input);
        $tokens = $this->tokenize($normalizedInput);

        $scores = [];
        $labels = Arr::unique(Arr::merge(Arr::keys($this->_keywordsByLabel), Arr::keys($this->_phrasesByLabel)));
        foreach ($labels as $label) {
            $label = (string)$label;
            $score = 0.0;
            $phrases = Arr::is($this->_phrasesByLabel[$label] ?? null) ? $this->_phrasesByLabel[$label] : [];
            foreach ($phrases as $phrase) {
                $phrase = (string)$phrase;
                $phraseTokens = $this->tokenize($phrase);
                if (Val::isEmpty($phraseTokens)) {
                    continue;
                }

                if ($phrase === $normalizedInput) {
                    $score += 10.0 + Arr::count($phraseTokens);
                    continue;
                }

                if (Arr::count($phraseTokens) > 1 && Str::contains($normalizedInput, $phrase)) {
                    $score += 5.0 + Arr::count($phraseTokens);
                }

                $shared = Arr::count(Arr::intersect($tokens, $phraseTokens));
                if ($shared > 0) {
                    $score += $shared / Arr::count($phraseTokens);
                }
            }

            $keywords = Arr::is($this->_keywordsByLabel[$label] ?? null) ? $this->_keywordsByLabel[$label] : [];
            $shared = Arr::intersect($tokens, $keywords);
            $score += Arr::count($shared);

            if ($score > 0.0) {
                $scores[$label] = $score;
            }
        }

        if (Val::isNotEmpty($scores)) {
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
        if (Val::isEmpty($pairs)) {
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

        if (Arr::is($scores) && Val::isNotEmpty($scores)) {
            $wrapped = Arr::make($scores);
            return $wrapped->keys()->get(0);
        }

        return null;
    }

    protected function buildKeywordMap(array $samples, array $labels): array
    {
        $map = [];
        $count = (int)Num::min(Arr::count($samples), Arr::count($labels));

        for ($i = 0; $i < $count; $i++) {
            $label = (string)$labels[$i];
            $sample = (string)$samples[$i];
            $tokens = $this->tokenize($sample);

            if (!Arr::hasKey($map, $label)) {
                $map[$label] = [];
            }

            $map[$label] = Arr::merge($map[$label], $tokens);
        }

        foreach ($map as $label => $keywords) {
            $map[$label] = Arr::unique($keywords);
        }

        return $map;
    }

    protected function buildPhraseMap(array $samples, array $labels): array
    {
        $map = [];
        $count = (int)Num::min(Arr::count($samples), Arr::count($labels));

        for ($i = 0; $i < $count; $i++) {
            $label = (string)$labels[$i];
            $sample = $this->normalizeText((string)$samples[$i]);
            if (Val::isEmpty($sample)) {
                continue;
            }

            if (!Arr::hasKey($map, $label)) {
                $map[$label] = [];
            }

            $map[$label][] = $sample;
        }

        foreach ($map as $label => $phrases) {
            $map[$label] = Arr::unique($phrases);
        }

        return $map;
    }

    protected function tokenize(string $input): array
    {
        $input = $this->normalizeText($input);
        if (Val::isEmpty($input)) {
            return [];
        }

        $tokens = preg_split('/\s+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = Arr::is($tokens) ? $tokens : [];
        $tokens = (new Collection($tokens))
            ->filter(function ($token) {
                return Val::isNotEmpty($token) && !Arr::hasKey(self::STOPWORDS, (string)$token);
            })
            ->toArray();

        return Arr::unique($tokens);
    }

    protected function normalizeText(string $input): string
    {
        $input = Str::make($input)->trim()->lower()->val();
        $input = (string)preg_replace('/[^a-z0-9]+/', ' ', $input);

        return Str::trim($input);
    }

    protected function buildTestPairs(array $samples, array $labels, float $testSize): array
    {
        $count = (int)Num::min(Arr::count($samples), Arr::count($labels));
        if ($count === 0) {
            return [];
        }

        $testCount = (int)Num::round($count * $testSize);
        if ($testCount < 1) {
            $testCount = $count;
        } elseif ($testCount > $count) {
            $testCount = $count;
        }

        $start = $count - $testCount;
        $sampleSlice = Arr::slice($samples, $start);
        $labelSlice = Arr::slice($labels, $start);

        $pairs = [];
        $sliceCount = (int)Num::min(Arr::count($sampleSlice), Arr::count($labelSlice));
        for ($i = 0; $i < $sliceCount; $i++) {
            $pairs[] = [
                'sample' => $sampleSlice[$i],
                'label' => $labelSlice[$i],
            ];
        }

        return $pairs;
    }
}
