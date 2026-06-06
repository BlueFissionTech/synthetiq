<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Training;

use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\Str;
use RuntimeException;

class RouteTrainer
{
    public const STATE_VERSION = 1;
    public const STAGE_KEYWORDS = 'intent_keywords';
    public const STAGE_ROUTE = 'route';
    public const STAGE_COMPLETE = 'complete';

    protected const DEFAULT_STOPWORDS = ['how', 'what', 'is', 'the', 'a', 'an', 'to', 'for', 'on', 'in'];

    /**
     * @param array<string, array<int, mixed>> $dialogue
     * @param array<string, array<string, mixed>> $intentBoosts
     * @return array{intents:int,routes:int,keywords:int,cache_key:string}
     */
    public static function train(SynthetIQ $ai, array $dialogue, array $intentBoosts = [], ?callable $progress = null, array $extra = []): array
    {
        return self::apply($ai, self::compile($dialogue, $intentBoosts, $extra), $progress);
    }

    /**
     * @param array<string, mixed> $state
     * @return array{intents:int,routes:int,keywords:int,cache_key:string}
     */
    public static function apply(SynthetIQ $ai, array $state, ?callable $progress = null): array
    {
        self::assertValidState($state);

        $intents = is_array($state['intents'] ?? null) ? $state['intents'] : [];
        $total = self::countStateStatements($state);
        $current = 0;
        $intentCount = 0;
        $keywordCount = 0;

        foreach ($intents as $category => $info) {
            $category = (string)$category;
            $intentCount++;

            $keywords = is_array($info['keywords'] ?? null) ? $info['keywords'] : [];
            $priorityBase = isset($info['priority']) && is_numeric($info['priority']) ? (int)$info['priority'] : null;

            if (!empty($keywords)) {
                $ai->addIntentKeywords($category, $keywords, $priorityBase);
                $keywordCount += count($keywords);
            }

            self::emit($progress, [
                'stage' => self::STAGE_KEYWORDS,
                'intent' => $category,
                'current' => $current,
                'total' => $total,
                'keywords' => count($keywords),
            ]);

            $targets = is_array($info['targets'] ?? null) ? $info['targets'] : [];
            $statements = is_array($info['statements'] ?? null) ? $info['statements'] : [];
            foreach ($statements as $statement) {
                $statement = (string)$statement;
                if (Str::trim($statement) === '') {
                    continue;
                }

                $ai->addRoute($statement, $category, $targets);
                $current++;

                self::emit($progress, [
                    'stage' => self::STAGE_ROUTE,
                    'intent' => $category,
                    'statement' => $statement,
                    'current' => $current,
                    'total' => $total,
                ]);
            }
        }

        self::emit($progress, [
            'stage' => self::STAGE_COMPLETE,
            'current' => $current,
            'total' => $total,
        ]);

        return [
            'intents' => $intentCount,
            'routes' => $current,
            'keywords' => $keywordCount,
            'cache_key' => (string)($state['cache_key'] ?? ''),
        ];
    }

    /**
     * @param array<string, array<int, mixed>> $dialogue
     * @param array<string, array<string, mixed>> $intentBoosts
     * @return array<string, mixed>
     */
    public static function compile(array $dialogue, array $intentBoosts = [], array $extra = []): array
    {
        $intents = [];
        $routeCount = 0;
        $keywordCount = 0;

        foreach ($dialogue as $category => $info) {
            $category = (string)$category;
            $info = is_array($info) ? $info : [];
            $boost = is_array($intentBoosts[$category] ?? null) ? $intentBoosts[$category] : [];

            $statements = self::normalizeStringList(is_array($info[1] ?? null) ? $info[1] : []);
            $keywords = self::keywordsForIntent($info, $boost);
            $priorityBase = isset($boost['priority']) && is_numeric($boost['priority']) ? (int)$boost['priority'] : null;

            $intents[$category] = [
                'targets' => self::normalizeTargets($info[0] ?? []),
                'statements' => $statements,
                'keywords' => $keywords,
                'priority' => $priorityBase,
            ];

            $routeCount += count($statements);
            $keywordCount += count($keywords);
        }

        $cacheKey = self::cacheKey($dialogue, $intentBoosts, $extra);

        return [
            'version' => self::STATE_VERSION,
            'cache_key' => $cacheKey,
            'meta' => [
                'intents' => count($intents),
                'routes' => $routeCount,
                'keywords' => $keywordCount,
                'extra' => self::normalize($extra),
            ],
            'intents' => $intents,
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    public static function saveState(array $state, string $path): void
    {
        self::assertValidState($state);

        $directory = dirname($path);
        if ($directory !== '' && !is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $payload = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            throw new RuntimeException('Unable to encode route training state.');
        }

        file_put_contents($path, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadState(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Route training state not found: {$path}");
        }

        $contents = file_get_contents($path);
        $state = json_decode((string)$contents, true);
        if (!is_array($state)) {
            throw new RuntimeException("Route training state is not valid JSON: {$path}");
        }

        self::assertValidState($state);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, array<int, mixed>> $dialogue
     * @param array<string, array<string, mixed>> $intentBoosts
     */
    public static function stateMatches(array $state, array $dialogue, array $intentBoosts = [], array $extra = []): bool
    {
        if (!isset($state['cache_key']) || !is_string($state['cache_key']) || $state['cache_key'] === '') {
            return false;
        }

        return hash_equals($state['cache_key'], self::cacheKey($dialogue, $intentBoosts, $extra));
    }

    /**
     * @param array<string, array<int, mixed>> $dialogue
     */
    public static function countRouteStatements(array $dialogue): int
    {
        return self::countStateStatements(self::compile($dialogue));
    }

    /**
     * @param array<string, array<int, mixed>> $dialogue
     * @param array<string, array<string, mixed>> $intentBoosts
     */
    public static function cacheKey(array $dialogue, array $intentBoosts = [], array $extra = []): string
    {
        return sha1(json_encode(self::normalize([
            'dialogue' => $dialogue,
            'intent_boosts' => $intentBoosts,
            'extra' => $extra,
        ])) ?: '');
    }

    /**
     * @param array<int, mixed> $info
     * @param array<string, mixed> $boost
     * @return array<int, string>
     */
    protected static function keywordsForIntent(array $info, array $boost): array
    {
        $keywords = is_array($info[2] ?? null) ? $info[2] : [];
        if (is_array($boost['keywords'] ?? null)) {
            $keywords = array_merge($keywords, $boost['keywords']);
        }

        $exclude = is_array($boost['exclude'] ?? null) ? $boost['exclude'] : [];

        return self::normalizeKeywords($keywords, array_merge(self::DEFAULT_STOPWORDS, $exclude));
    }

    /**
     * @param array<int, mixed> $keywords
     * @param array<int, mixed> $exclude
     * @return array<int, string>
     */
    public static function normalizeKeywords(array $keywords, array $exclude = []): array
    {
        $excludeSet = [];
        foreach ($exclude as $value) {
            $excludeSet[Str::lower(Str::trim((string)$value))] = true;
        }

        $normalized = [];
        foreach ($keywords as $keyword) {
            $keyword = Str::lower(Str::trim((string)$keyword));
            if ($keyword === '' || isset($excludeSet[$keyword])) {
                continue;
            }

            $normalized[$keyword] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param array<string, mixed> $state
     */
    protected static function assertValidState(array $state): void
    {
        if ((int)($state['version'] ?? 0) !== self::STATE_VERSION) {
            throw new RuntimeException('Unsupported route training state version.');
        }

        if (!isset($state['cache_key']) || !is_string($state['cache_key']) || $state['cache_key'] === '') {
            throw new RuntimeException('Route training state is missing a cache key.');
        }

        if (!is_array($state['intents'] ?? null)) {
            throw new RuntimeException('Route training state is missing intents.');
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    protected static function countStateStatements(array $state): int
    {
        $total = 0;
        $intents = is_array($state['intents'] ?? null) ? $state['intents'] : [];
        foreach ($intents as $info) {
            if (is_array($info['statements'] ?? null)) {
                $total += count(array_filter($info['statements'], static fn($statement): bool => Str::trim((string)$statement) !== ''));
            }
        }

        return $total;
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    protected static function normalizeStringList(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            $value = Str::trim((string)$value);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    protected static function normalizeTargets(mixed $targets): array
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }

        return self::normalizeStringList($targets);
    }

    protected static function emit(?callable $progress, array $event): void
    {
        if (!$progress) {
            return;
        }

        $progress($event);
    }

    protected static function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalize($item);
        }

        if (!array_is_list($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }
}
