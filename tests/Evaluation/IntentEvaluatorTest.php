<?php

namespace BlueFission\SynthetIQ\Tests\Evaluation;

use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Language\ContractionNormalizer;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use BlueFission\SynthetIQ\Evaluation\IntentEvaluator;
use BlueFission\SynthetIQ\Intents\Classifier;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Tests\Support\FakeInterpreter;
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

        $modelDir = __DIR__ . '/../../models/ml/';
        if (!is_dir($modelDir)) {
            mkdir($modelDir, 0777, true);
        }

        $analyzer = new KeywordTopicAnalyzer(new NaiveBayesTextClassification, $modelDir);
        $ai = new SynthetIQ(new FakeInterpreter(), $analyzer);
        $this->trainRoutes($ai, $dialogue, $intentBoosts);

        $classifier = new Classifier($analyzer);
        $evaluator = new IntentEvaluator([ContractionNormalizer::class, 'normalize']);
        $result = $evaluator->evaluate($classifier, $cases);

        $minAccuracy = $thresholds['intent_accuracy_min'] ?? 0.2;
        $this->assertGreaterThanOrEqual($minAccuracy, $result['accuracy']);
        $this->assertSame(count($cases), $result['total']);
    }

    private function trainRoutes(SynthetIQ $ai, array $dialogue, array $intentBoosts): void
    {
        $stopwords = ['how', 'what', 'is', 'the', 'a', 'an', 'to', 'for', 'on', 'in'];

        foreach ($dialogue as $category => $info) {
            $boost = $intentBoosts[$category] ?? [];
            $keywords = $info[2] ?? [];
            if (!empty($boost['keywords'])) {
                $keywords = array_merge($keywords, $boost['keywords']);
            }
            $exclude = $boost['exclude'] ?? [];
            $keywords = $this->normalizeKeywords($keywords, array_merge($stopwords, $exclude));
            $priorityBase = $boost['priority'] ?? null;
            $ai->addIntentKeywords($category, $keywords, $priorityBase);

            foreach ($info[1] as $statement) {
                $ai->addRoute($statement, $category, $info[0]);
            }
        }
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
}
