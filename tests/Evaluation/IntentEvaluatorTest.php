<?php

namespace BlueFission\SynthetIQ\Tests\Evaluation;

use BlueFission\Arr;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Intent\Matcher;
use BlueFission\Automata\Language\ContractionNormalizer;
use BlueFission\SynthetIQ\Evaluation\IntentEvaluator;
use BlueFission\SynthetIQ\Intents\Classifier;
use BlueFission\SynthetIQ\Tests\Support\MatcherResetter;
use PHPUnit\Framework\TestCase;

class IntentEvaluatorTest extends TestCase
{
    protected function setUp(): void
    {
        MatcherResetter::reset();
    }

    public function testIntentAccuracyMeetsBaseline(): void
    {
        $dialogue = require __DIR__ . '/../../sample_configs/dialogue.php';
        $intentBoosts = require __DIR__ . '/../../sample_configs/intent_boosts.php';
        $cases = require __DIR__ . '/../../sample_configs/eval_cases.php';
        $thresholds = require __DIR__ . '/../../sample_configs/eval_thresholds.php';

        $analyzer = $this->createKeywordAnalyzer();
        $matcher = new Matcher($analyzer);
        $this->trainMatcher($matcher, $dialogue, $intentBoosts);

        $classifier = new Classifier($analyzer, $matcher);
        $evaluator = new IntentEvaluator([ContractionNormalizer::class, 'normalize']);
        $result = $evaluator->evaluate($classifier, $cases);

        $minAccuracy = $thresholds['intent_accuracy_min'] ?? 0.2;
        $this->assertGreaterThanOrEqual($minAccuracy, $result['accuracy']);
        $this->assertSame(count($cases), $result['total']);
    }

    private function trainMatcher(Matcher $matcher, array $dialogue, array $intentBoosts): void
    {
        $stopwords = ['how', 'what', 'is', 'the', 'a', 'an', 'to', 'for', 'on', 'in'];

        foreach ($dialogue as $category => $info) {
            $intent = $matcher->getIntent($category);
            if (!$intent) {
                $intent = new Intent($category, $category);
                $matcher->registerIntent($intent);
            }

            $boost = $intentBoosts[$category] ?? [];
            $keywords = $info[2] ?? [];
            if (!empty($boost['keywords'])) {
                $keywords = array_merge($keywords, $boost['keywords']);
            }
            $exclude = $boost['exclude'] ?? [];
            $keywords = $this->normalizeKeywords($keywords, array_merge($stopwords, $exclude));
            $priorityBase = $boost['priority'] ?? null;
            $this->addKeywords($intent, $keywords, $priorityBase ?? 12);

            foreach ($info[1] as $statement) {
                $this->addKeywords($intent, [(string)$statement], 10);
            }
        }
    }

    private function addKeywords(Intent $intent, array $keywords, int $priorityBase): void
    {
        foreach ($keywords as $keyword) {
            $keyword = trim((string)$keyword);
            if ($keyword === '') {
                continue;
            }

            $intent->addCriteria('keywords', [
                'word' => $keyword,
                'priority' => $this->computePriority($keyword, $priorityBase),
            ]);
        }
    }

    private function computePriority(string $text, int $base): float
    {
        $priority = (float)$base;
        $length = strlen($text);

        return $priority - ($length / ($base / 2));
    }

    private function normalizeKeywords(array $keywords, array $exclude = []): array
    {
        $excludeSet = [];
        foreach ($exclude as $value) {
            $excludeSet[strtolower(trim((string)$value))] = true;
        }

        $normalized = [];
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim((string)$keyword));
            if ($keyword === '' || isset($excludeSet[$keyword])) {
                continue;
            }
            $normalized[$keyword] = true;
        }

        return array_keys($normalized);
    }

    private function createKeywordAnalyzer(): IAnalyzer
    {
        return new class implements IAnalyzer {
            public function analyze(string $input, Context $context, array $keywords): Arr
            {
                $input = strtolower(trim($input));
                $tokens = $this->tokenize($input);
                $scores = [];

                foreach ($keywords as $label => $phrases) {
                    foreach ($phrases as $phrase) {
                        $text = strtolower(trim((string)($phrase['text'] ?? $phrase['word'] ?? '')));
                        if ($text === '') {
                            continue;
                        }

                        if (!$this->matches($input, $tokens, $text)) {
                            continue;
                        }

                        $weight = (float)($phrase['weight'] ?? $phrase['priority'] ?? 1);
                        $phraseWeight = max(1, count($this->tokenize($text)));
                        $scores[$label] = ($scores[$label] ?? 0) + ($weight * $phraseWeight);
                    }
                }

                if (!empty($scores)) {
                    arsort($scores);
                }

                return Arr::make($scores);
            }

            private function matches(string $input, array $tokens, string $phrase): bool
            {
                if (str_contains($phrase, ' ')) {
                    return str_contains($input, $phrase);
                }

                return in_array($phrase, $tokens, true);
            }

            private function tokenize(string $text): array
            {
                $parts = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

                return is_array($parts) ? $parts : [];
            }
        };
    }
}
