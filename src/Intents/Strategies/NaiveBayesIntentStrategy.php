<?php

namespace BlueFission\SynthetIQ\Intents\Strategies;

use BlueFission\Automata\Context;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use BlueFission\Automata\Strategy\Strategy;
use BlueFission\Arr;
use BlueFission\Net\HTTP;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\DevElation as Dev;

class NaiveBayesIntentStrategy extends Strategy implements ContextAwareStrategyInterface
{
    protected NaiveBayesTextClassification $_classifier;
    protected float $_accuracy = 0.0;
    protected bool $_trained = false;
    protected int $_minSamples = 2;
    protected int $_minLabels = 2;
    protected int $_sampleCount = 0;
    protected int $_labelCount = 0;
    protected float $_confidenceFloor = 0.2;
    protected float $_confidenceCap = 0.85;
    protected float $_unknownCap = 0.5;
    protected float $_smallSamplePenalty = 0.1;
    protected bool $_cacheEnabled = true;
    protected bool $_forceRetrain = false;
    protected string $_cacheKey = '';
    protected ?string $_cacheDir = null;
    protected ?string $_modelPath = null;
    protected ?array $_cacheMeta = null;

    public function __construct(?NaiveBayesTextClassification $classifier = null, array $options = [])
    {
        $this->_classifier = $classifier ?? new NaiveBayesTextClassification();

        if (Val::is($options['min_samples'] ?? null)) {
            $this->_minSamples = (int)Num::max(1, (int)$options['min_samples']);
        }
        if (Val::is($options['min_labels'] ?? null)) {
            $this->_minLabels = (int)Num::max(1, (int)$options['min_labels']);
        }
        if (Val::is($options['confidence_floor'] ?? null)) {
            $this->_confidenceFloor = $this->clampScore((float)$options['confidence_floor']);
        }
        if (Val::is($options['confidence_cap'] ?? null)) {
            $this->_confidenceCap = $this->clampScore((float)$options['confidence_cap']);
        }
        if (Val::is($options['unknown_cap'] ?? null)) {
            $this->_unknownCap = $this->clampScore((float)$options['unknown_cap']);
        }
        if (Val::is($options['small_sample_penalty'] ?? null)) {
            $this->_smallSamplePenalty = Num::max(0.0, (float)$options['small_sample_penalty']);
        }

        $this->_cacheEnabled = !((bool)($options['disable_cache'] ?? false));
        $this->_forceRetrain = (bool)($options['force_retrain'] ?? false);
        $this->_cacheKey = (string)($options['cache_key'] ?? '');
        $this->_cacheDir = Val::is($options['cache_dir'] ?? null) ? (string)$options['cache_dir'] : null;
        $this->_modelPath = Val::is($options['model_path'] ?? null) ? (string)$options['model_path'] : null;
    }

    public function setContext(Context $context): void
    {
        // Intentionally unused; classifier is context-agnostic.
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $samples = Dev::apply('synthetiq.intent.strategy.naive_bayes.train.samples', $samples);
        $labels = Dev::apply('synthetiq.intent.strategy.naive_bayes.train.labels', $labels);

        $dataset = $this->normalizeDataset($samples, $labels);
        $sampleCount = Arr::count($dataset['samples']);
        $uniqueLabels = Arr::unique($dataset['labels']);
        $this->_sampleCount = $sampleCount;
        $this->_labelCount = Arr::count($uniqueLabels);

        if ($sampleCount < $this->_minSamples || $this->_labelCount < $this->_minLabels) {
            $this->_accuracy = 0.0;
            $this->_trained = false;
            Dev::do('synthetiq.intent.strategy.naive_bayes.skipped', [
                'samples' => $sampleCount,
                'labels' => $this->_labelCount,
            ]);
            return;
        }

        if ($this->shouldLoadCache() && $this->tryLoadCachedModel()) {
            return;
        }

        $testSize = $this->normalizeTestSize($testSize);

        try {
            $this->_classifier->train($dataset['samples'], $dataset['labels'], $testSize);
            $this->_accuracy = $this->_classifier->accuracy();
            $this->_trained = true;
            Dev::do('synthetiq.intent.strategy.naive_bayes.trained', [
                'accuracy' => $this->_accuracy,
            ]);

            if ($this->shouldSaveCache()) {
                $this->saveCachedModel();
            }
        } catch (\Throwable $e) {
            $this->_accuracy = 0.0;
            $this->_trained = false;
            Dev::do('synthetiq.intent.strategy.naive_bayes.failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function predict($input)
    {
        $input = Dev::apply('synthetiq.intent.strategy.naive_bayes.predict.input', $input);

        if (!$this->_trained) {
            return Arr::make();
        }

        try {
            $label = $this->_classifier->predict((string)$input);
        } catch (\Throwable $e) {
            return Arr::make();
        }

        $confidence = $this->computeConfidence();
        $fallback = $this->clampScore($confidence * 0.5);
        if ($fallback > $this->_unknownCap) {
            $fallback = $this->_unknownCap;
        }
        $scores = [$label => $confidence];

        if ($fallback > 0.0) {
            $scores['unknown.intent'] = $fallback;
        }

        if (Val::isNotEmpty($scores)) {
            arsort($scores);
        }

        $scoresArr = Arr::make($scores);

        Dev::do('synthetiq.intent.strategy.naive_bayes.predicted', [
            'scores' => $scoresArr->toArray(),
        ]);

        return $scoresArr;
    }

    public function accuracy(): float
    {
        return $this->_accuracy;
    }

    protected function normalizeDataset(array $samples, array $labels): array
    {
        $dataset = [
            'samples' => [],
            'labels' => [],
        ];

        $count = (int)Num::min(Arr::count($samples), Arr::count($labels));
        for ($i = 0; $i < $count; $i++) {
            $sample = Str::trim((string)$samples[$i]);
            $label = Str::trim((string)$labels[$i]);

            if ($sample === '' || $label === '') {
                continue;
            }

            $dataset['samples'][] = $sample;
            $dataset['labels'][] = $label;
        }

        return $dataset;
    }

    protected function clampScore(float $score): float
    {
        if ($score < 0.0) {
            return 0.0;
        }
        if ($score > 1.0) {
            return 1.0;
        }
        return $score;
    }

    protected function shouldLoadCache(): bool
    {
        return $this->_cacheEnabled && !$this->_forceRetrain;
    }

    protected function shouldSaveCache(): bool
    {
        return $this->_cacheEnabled;
    }

    protected function resolveModelPath(): ?string
    {
        if (Val::isNotEmpty($this->_modelPath)) {
            return $this->_modelPath;
        }

        $dir = $this->_cacheDir ?: 'models';
        $dir = rtrim($dir, "/\\");
        if ($dir === '') {
            $dir = 'models';
        }

        return $dir . DIRECTORY_SEPARATOR . 'intent_naive_bayes.phpml';
    }

    protected function cacheMetaPath(): ?string
    {
        $modelPath = $this->resolveModelPath();
        if (!$modelPath) {
            return null;
        }

        return $modelPath . '.json';
    }

    protected function tryLoadCachedModel(): bool
    {
        $modelPath = $this->resolveModelPath();
        if (Val::isEmpty($modelPath) || !file_exists($modelPath)) {
            return false;
        }

        if (!$this->cacheKeyMatches()) {
            return false;
        }

        try {
            if ($this->_classifier->loadModel($modelPath)) {
                $this->_trained = true;
                $this->_accuracy = $this->cacheAccuracy();
                Dev::do('synthetiq.intent.strategy.naive_bayes.cache_hit', [
                    'model_path' => $modelPath,
                ]);
                return true;
            }
        } catch (\Throwable $e) {
            Dev::do('synthetiq.intent.strategy.naive_bayes.cache_error', [
                'model_path' => $modelPath,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    protected function saveCachedModel(): void
    {
        $modelPath = $this->resolveModelPath();
        if (!$modelPath) {
            return;
        }

        $directory = dirname($modelPath);
        $this->ensureDirectory($directory);

        $saved = $this->_classifier->saveModel($modelPath);
        if ($saved) {
            $this->writeCacheMeta([
                'cache_key' => $this->_cacheKey,
                'accuracy' => $this->_accuracy,
                'samples' => $this->_sampleCount,
                'labels' => $this->_labelCount,
                'saved_at' => time(),
            ]);
            Dev::do('synthetiq.intent.strategy.naive_bayes.cache_saved', [
                'model_path' => $modelPath,
            ]);
        }
    }

    protected function cacheKeyMatches(): bool
    {
        if ($this->_cacheKey === '') {
            return true;
        }

        $meta = $this->readCacheMeta();
        $cacheKey = (string)($meta['cache_key'] ?? '');

        return $cacheKey !== '' && hash_equals($cacheKey, $this->_cacheKey);
    }

    protected function cacheAccuracy(): float
    {
        $meta = $this->readCacheMeta();
        if (!isset($meta['accuracy'])) {
            return 0.0;
        }

        return (float)$meta['accuracy'];
    }

    protected function readCacheMeta(): array
    {
        if ($this->_cacheMeta !== null) {
            return $this->_cacheMeta;
        }

        $metaPath = $this->cacheMetaPath();
        if (Val::isEmpty($metaPath) || !file_exists($metaPath)) {
            $this->_cacheMeta = [];
            return $this->_cacheMeta;
        }

        $contents = file_get_contents($metaPath);
        $data = HTTP::jsonDecode((string)$contents, true, []);
        if (!Arr::is($data)) {
            $data = [];
        }

        $this->_cacheMeta = $data;
        return $data;
    }

    protected function writeCacheMeta(array $data): void
    {
        $metaPath = $this->cacheMetaPath();
        if (!$metaPath) {
            return;
        }

        $this->_cacheMeta = $data;
        $payload = HTTP::jsonEncode($data);
        file_put_contents($metaPath, $payload === false ? '{}' : $payload);
    }

    protected function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        mkdir($directory, 0777, true);
    }

    protected function normalizeTestSize(float $testSize): float
    {
        if ($testSize <= 0.0 || $testSize >= 1.0) {
            return 0.2;
        }

        return $testSize;
    }

    protected function computeConfidence(): float
    {
        $accuracy = $this->_accuracy;
        if ($accuracy < 0.0) {
            $accuracy = 0.0;
        }

        $confidence = $this->_confidenceFloor + ((1.0 - $this->_confidenceFloor) * $accuracy);

        if ($this->_sampleCount > 0 && $this->_sampleCount < 5) {
            $confidence -= $this->_smallSamplePenalty;
        }

        $confidence = $this->clampScore($confidence);

        if ($confidence < $this->_confidenceFloor) {
            $confidence = $this->_confidenceFloor;
        }

        if ($confidence > $this->_confidenceCap) {
            $confidence = $this->_confidenceCap;
        }

        return $confidence;
    }
}
