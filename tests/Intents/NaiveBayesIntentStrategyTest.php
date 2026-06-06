<?php

namespace BlueFission\SynthetIQ\Tests\Intents;

use BlueFission\Arr;
use BlueFission\SynthetIQ\Intents\Strategies\NaiveBayesIntentStrategy;
use PHPUnit\Framework\TestCase;

class NaiveBayesIntentStrategyTest extends TestCase
{
    private function makeTempPath(string $suffix): string
    {
        $dir = __DIR__ . '/../../artifacts/tmp';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . '/' . $suffix;
    }

    public function testSkipsTrainingWhenDatasetIsTooSmall(): void
    {
        $strategy = new NaiveBayesIntentStrategy(null, ['min_samples' => 2, 'min_labels' => 2, 'disable_cache' => true]);
        $strategy->train(['hello'], ['greeting'], 0.0);

        $scores = $strategy->predict('hello');
        $this->assertInstanceOf(Arr::class, $scores);
        $this->assertSame(0, $scores->count());
    }

    public function testPredictsWhenTrained(): void
    {
        $strategy = new NaiveBayesIntentStrategy(null, ['min_samples' => 2, 'min_labels' => 2, 'disable_cache' => true]);
        $samples = ['hello', 'hi', 'bye', 'goodbye'];
        $labels = ['greeting', 'greeting', 'farewell', 'farewell'];

        $strategy->train($samples, $labels, 0.0);
        $scores = $strategy->predict('hello');

        $this->assertInstanceOf(Arr::class, $scores);
        $top = $scores->keys()->get(0);
        $this->assertNotNull($top);
        $this->assertContains($top, ['greeting', 'farewell']);
    }

    public function testCacheWritesModelWhenEnabled(): void
    {
        $modelPath = $this->makeTempPath('intent_cache_' . uniqid('', true) . '.phpml');

        $strategy = new NaiveBayesIntentStrategy(null, [
            'model_path' => $modelPath,
            'cache_key' => 'cache-key-1',
        ]);

        $samples = ['hello', 'hi', 'bye', 'goodbye', 'see ya', 'later'];
        $labels = ['greeting', 'greeting', 'farewell', 'farewell', 'farewell', 'farewell'];

        $strategy->train($samples, $labels, 0.2);

        $this->assertFileExists($modelPath);
        $this->assertFileExists($modelPath . '.json');

        $meta = json_decode((string)file_get_contents($modelPath . '.json'), true);
        $this->assertIsArray($meta);
        $this->assertSame('cache-key-1', $meta['cache_key'] ?? null);
    }

    public function testCacheSkipsWriteWhenDisabled(): void
    {
        $modelPath = $this->makeTempPath('intent_cache_' . uniqid('', true) . '.phpml');

        $strategy = new NaiveBayesIntentStrategy(null, [
            'model_path' => $modelPath,
            'cache_key' => 'cache-key-2',
            'disable_cache' => true,
        ]);

        $samples = ['hello', 'hi', 'bye', 'goodbye', 'see ya', 'later'];
        $labels = ['greeting', 'greeting', 'farewell', 'farewell', 'farewell', 'farewell'];

        $strategy->train($samples, $labels, 0.2);

        $this->assertFileDoesNotExist($modelPath);
        $this->assertFileDoesNotExist($modelPath . '.json');
    }
}
