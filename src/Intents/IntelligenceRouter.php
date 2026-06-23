<?php

namespace BlueFission\SynthetIQ\Intents;

use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Matcher;
use BlueFission\Automata\Intelligence;
use BlueFission\Automata\Strategy\IStrategy;
use BlueFission\SynthetIQ\Intents\Strategies\ContextAwareStrategyInterface;
use BlueFission\SynthetIQ\Intents\Strategies\KeywordOverlapStrategy;
use BlueFission\SynthetIQ\Intents\Strategies\MatcherIntentStrategy;
use BlueFission\SynthetIQ\Intents\Strategies\NaiveBayesIntentStrategy;
use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\DevElation as Dev;

class IntelligenceRouter extends Classifier
{
    protected Intelligence $_intelligence;
    protected float $_testSize = 0.2;
    protected bool $_needsTraining = true;
    protected array $_contextAwareStrategies = [];
    protected array $_strategies = [];
    protected array $_strategyNames = [];
    protected array $_strategyWeights = [];
    protected array $_strategyThresholds = [];
    protected array $_lastDiagnostics = [];

    public function __construct(IAnalyzer $analyzer, ?Matcher $matcher = null, array $options = [])
    {
        parent::__construct($analyzer, $matcher);

        $minThreshold = Val::is($options['min_threshold'] ?? null) ? (float)$options['min_threshold'] : 0.0;
        $this->_intelligence = $options['intelligence'] ?? new Intelligence($minThreshold);
        if (Val::is($options['test_size'] ?? null)) {
            $this->_testSize = (float)$options['test_size'];
        }
        $this->_strategyWeights = Arr::is($options['strategy_weights'] ?? null) ? $options['strategy_weights'] : [];
        $this->_strategyThresholds = Arr::is($options['strategy_thresholds'] ?? null) ? $options['strategy_thresholds'] : [];

        $this->registerDefaultStrategies($options);

        $this->_intelligence->onPrediction(function ($event) {
            Dev::do('synthetiq.intent.router.prediction', $event);
        });
    }

    public function registerStrategy(string $name, IStrategy $strategy): void
    {
        $name = (string)$name;
        $this->_intelligence->registerStrategy($strategy, $name);
        $this->_strategies[$name] = $strategy;
        $this->_strategyNames[$name] = $name;

        if ($strategy instanceof ContextAwareStrategyInterface) {
            $this->_contextAwareStrategies[$name] = $strategy;
        }

        $this->markDirty();

        Dev::do('synthetiq.intent.router.strategy_registered', [
            'name' => $name,
            'strategy' => $strategy,
        ]);
    }

    public function markDirty(): void
    {
        $this->_needsTraining = true;
    }

    public function score(string $input, Context $context): ?Arr
    {
        $input = Dev::apply('synthetiq.intent.router.score.input', $input);

        $this->trainIfNeeded();
        $this->applyContext($context);

        $scores = $this->scoreWithStrategies($input);

        $blockedByThreshold = (bool)($this->_lastDiagnostics['blocked_by_threshold'] ?? false);

        if ((!$scores || $scores->count() === 0) && !$blockedByThreshold) {
            $output = $this->_intelligence->predict($input);
            $scores = $this->normalizeScores($output);
        }

        if ((!$scores || $scores->count() === 0) && !$blockedByThreshold) {
            $scores = parent::score($input, $context);
        }

        return Dev::apply('synthetiq.intent.router.score.output', $scores);
    }

    public function lastDiagnostics(): array
    {
        return $this->_lastDiagnostics;
    }

    public function labelFromScores(string $input, ?Arr $scores): ?string
    {
        if (($this->_lastDiagnostics['blocked_by_threshold'] ?? false) && (!$scores instanceof Arr || $scores->count() === 0)) {
            return null;
        }

        return parent::labelFromScores($input, $scores);
    }

    public function setStrategyWeight(string $name, float $weight): void
    {
        $this->_strategyWeights[$name] = Num::max(0.0, $weight);
    }

    public function setStrategyThreshold(string $name, float $threshold): void
    {
        $this->_strategyThresholds[$name] = Num::max(0.0, $threshold);
    }

    protected function registerDefaultStrategies(array $options): void
    {
        $enableMatcher = $options['enable_matcher'] ?? true;
        if ($enableMatcher) {
            $this->registerStrategy('matcher', new MatcherIntentStrategy($this->_matcher, new Context()));
        }

        $enableOverlap = $options['enable_keyword_overlap'] ?? true;
        if ($enableOverlap) {
            $this->registerStrategy('keyword_overlap', new KeywordOverlapStrategy());
        }

        $enableNaiveBayes = $options['enable_naive_bayes'] ?? true;
        if ($enableNaiveBayes) {
            $bayesOptions = $options['naive_bayes'] ?? [];
            $this->registerStrategy('naive_bayes', new NaiveBayesIntentStrategy(null, $bayesOptions));
        }

        $extraStrategies = $options['strategies'] ?? [];
        if (Arr::is($extraStrategies)) {
            foreach ($extraStrategies as $name => $strategy) {
                if ($strategy instanceof IStrategy) {
                    $this->registerStrategy((string)$name, $strategy);
                }
            }
        }
    }

    protected function trainIfNeeded(): void
    {
        if (!$this->_needsTraining) {
            return;
        }

        $training = $this->buildTrainingSet();
        $samples = $training['samples'];
        $labels = $training['labels'];

        if (Val::isEmpty($samples) || Val::isEmpty($labels)) {
            $this->_needsTraining = false;
            Dev::do('synthetiq.intent.router.train_skipped', ['reason' => 'no_samples']);
            return;
        }

        $this->_intelligence->train($samples, $labels, $this->_testSize);
        $this->_needsTraining = false;

        Dev::do('synthetiq.intent.router.trained', [
            'samples' => Arr::count($samples),
            'labels' => Arr::count($labels),
        ]);
    }

    protected function buildTrainingSet(): array
    {
        $intents = $this->_matcher->getIntents();
        $samples = [];
        $labels = [];

        foreach ($intents as $label => $intent) {
            $criteria = $intent->getCriteria();
            $keywords = $criteria['keywords'] ?? [];
            if (Val::isEmpty($keywords)) {
                continue;
            }

            foreach ($keywords as $keyword) {
                $word = Str::trim((string)($keyword['word'] ?? ''));
                if (Val::isEmpty($word)) {
                    continue;
                }

                $samples[] = Str::lower($word);
                $labels[] = (string)$label;
            }
        }

        $samples = Dev::apply('synthetiq.intent.router.training.samples', $samples);
        $labels = Dev::apply('synthetiq.intent.router.training.labels', $labels);

        return [
            'samples' => $samples,
            'labels' => $labels,
        ];
    }

    protected function applyContext(Context $context): void
    {
        foreach ($this->_contextAwareStrategies as $strategy) {
            $strategy->setContext($context);
        }
    }

    protected function normalizeScores($output): ?Arr
    {
        if ($output instanceof Arr) {
            return $output;
        }

        if (Arr::is($output)) {
            return Arr::make($output);
        }

        if (Str::is($output) && Val::isNotEmpty($output)) {
            return Arr::make([$output => 1.0]);
        }

        return null;
    }

    protected function scoreWithStrategies(string $input): ?Arr
    {
        $combined = [];
        $diagnostics = [
            'input' => $input,
            'strategies' => [],
            'combined' => [],
            'blocked_by_threshold' => false,
        ];
        $sawScores = false;

        foreach ($this->_strategies as $name => $strategy) {
            $raw = $strategy->predict($input);
            $scores = $this->normalizeScores($raw);
            $scoreData = $scores instanceof Arr ? $scores->toArray() : [];
            $weight = $this->strategyWeight($name, $strategy);
            $threshold = (float)($this->_strategyThresholds[$name] ?? 0.0);
            $accepted = [];

            foreach ($scoreData as $label => $score) {
                if (!Num::is($score)) {
                    continue;
                }

                $score = (float)$score;
                $sawScores = true;
                if ($score < $threshold) {
                    continue;
                }

                $accepted[$label] = $score;
                $combined[$label] = ($combined[$label] ?? 0.0) + ($score * $weight);
            }

            $diagnostics['strategies'][$name] = [
                'weight' => $weight,
                'threshold' => $threshold,
                'accuracy' => $strategy->accuracy(),
                'scores' => $scoreData,
                'accepted' => $accepted,
            ];
        }

        if (Val::isNotEmpty($combined)) {
            arsort($combined);
        }

        $diagnostics['blocked_by_threshold'] = $sawScores && Val::isEmpty($combined);
        $diagnostics['combined'] = $combined;
        $this->_lastDiagnostics = $diagnostics;

        Dev::do('synthetiq.intent.router.ensemble_scored', $diagnostics);

        return Val::isEmpty($combined) ? null : Arr::make($combined);
    }

    protected function strategyWeight(string $name, IStrategy $strategy): float
    {
        if (Val::is($this->_strategyWeights[$name] ?? null) && Num::is($this->_strategyWeights[$name])) {
            return Num::max(0.0, (float)$this->_strategyWeights[$name]);
        }

        $accuracy = $strategy->accuracy();
        if ($accuracy > 0.0) {
            return $accuracy;
        }

        return 1.0;
    }
}
