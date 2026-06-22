<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Training;

use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\Data\FileSystem;
use BlueFission\Func;
use BlueFission\Net\HTTP;
use BlueFission\Num;
use BlueFission\Security\Hash;
use BlueFission\Str;
use BlueFission\Val;
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

        $intents = Arr::is($state['intents'] ?? null) ? $state['intents'] : [];
        $total = self::countStateStatements($state);
        $current = 0;
        $intentCount = 0;
        $keywordCount = 0;

        foreach ($intents as $category => $info) {
            $category = (string)$category;
            $intentCount++;

            $keywords = Arr::is($info['keywords'] ?? null) ? $info['keywords'] : [];
            $priorityBase = Val::is($info['priority'] ?? null) && Num::is($info['priority']) ? (int)$info['priority'] : null;

            if (Val::isNotEmpty($keywords)) {
                $ai->addIntentKeywords($category, $keywords, $priorityBase);
                $keywordCount += Arr::count($keywords);
            }

            self::emit($progress, [
                'stage' => self::STAGE_KEYWORDS,
                'intent' => $category,
                'current' => $current,
                'total' => $total,
                'keywords' => Arr::count($keywords),
            ]);

            $targets = Arr::is($info['targets'] ?? null) ? $info['targets'] : [];
            $statements = Arr::is($info['statements'] ?? null) ? $info['statements'] : [];
            foreach ($statements as $statement) {
                $statement = (string)$statement;
                if (Val::isEmpty(Str::trim($statement))) {
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
            $info = Arr::is($info) ? $info : [];
            $boost = Arr::is($intentBoosts[$category] ?? null) ? $intentBoosts[$category] : [];

            $statements = self::normalizeStringList(Arr::is($info[1] ?? null) ? $info[1] : []);
            $keywords = self::keywordsForIntent($info, $boost);
            $priorityBase = Val::is($boost['priority'] ?? null) && Num::is($boost['priority']) ? (int)$boost['priority'] : null;

            $intents[$category] = [
                'targets' => self::normalizeTargets($info[0] ?? []),
                'statements' => $statements,
                'keywords' => $keywords,
                'priority' => $priorityBase,
            ];

            $routeCount += Arr::count($statements);
            $keywordCount += Arr::count($keywords);
        }

        $cacheKey = self::cacheKey($dialogue, $intentBoosts, $extra);

        return [
            'version' => self::STATE_VERSION,
            'cache_key' => $cacheKey,
            'meta' => [
                'intents' => Arr::count($intents),
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
        if (Val::isNotEmpty($directory) && !is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $payload = HTTP::jsonEncode($state);
        if (!Str::is($payload)) {
            throw new RuntimeException('Unable to encode route training state.');
        }

        file_put_contents($path, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadState(string $path): array
    {
        if (!FileSystem::fileExists($path)) {
            throw new RuntimeException("Route training state not found: {$path}");
        }

        $contents = file_get_contents($path);
        $state = HTTP::jsonDecode((string)$contents, true, []);
        if (!Arr::is($state)) {
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
        if (!self::hasCacheKey($state)) {
            return false;
        }

        return (new Hash('sha1'))->verify(
            self::cachePayload($dialogue, $intentBoosts, $extra),
            (string)$state['cache_key'],
            'sha1'
        );
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
        return Hash::value(self::cachePayload($dialogue, $intentBoosts, $extra), 'sha1');
    }

    /**
     * @param array<int, mixed> $info
     * @param array<string, mixed> $boost
     * @return array<int, string>
     */
    protected static function keywordsForIntent(array $info, array $boost): array
    {
        $keywords = Arr::is($info[2] ?? null) ? $info[2] : [];
        if (Arr::is($boost['keywords'] ?? null)) {
            $keywords = Arr::merge($keywords, $boost['keywords']);
        }

        $exclude = Arr::is($boost['exclude'] ?? null) ? $boost['exclude'] : [];

        return self::normalizeKeywords($keywords, Arr::merge(self::DEFAULT_STOPWORDS, $exclude));
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
            if (Val::isEmpty($keyword) || Arr::hasKey($excludeSet, $keyword)) {
                continue;
            }

            $normalized[$keyword] = true;
        }

        return Arr::keys($normalized);
    }

    /**
     * @param array<string, mixed> $state
     */
    protected static function assertValidState(array $state): void
    {
        if ((int)($state['version'] ?? 0) !== self::STATE_VERSION) {
            throw new RuntimeException('Unsupported route training state version.');
        }

        if (!self::hasCacheKey($state)) {
            throw new RuntimeException('Route training state is missing a cache key.');
        }

        if (!Arr::is($state['intents'] ?? null)) {
            throw new RuntimeException('Route training state is missing intents.');
        }
    }

    protected static function hasCacheKey(array $state): bool
    {
        return Arr::hasKey($state, 'cache_key')
            && Str::is($state['cache_key'])
            && Val::isNotEmpty($state['cache_key']);
    }

    /**
     * @param array<string, array<int, mixed>> $dialogue
     * @param array<string, array<string, mixed>> $intentBoosts
     */
    protected static function cachePayload(array $dialogue, array $intentBoosts = [], array $extra = []): string
    {
        $payload = HTTP::jsonEncode(self::normalize([
            'dialogue' => $dialogue,
            'intent_boosts' => $intentBoosts,
            'extra' => $extra,
        ]));

        return Str::is($payload) ? $payload : '';
    }

    /**
     * @param array<string, mixed> $state
     */
    protected static function countStateStatements(array $state): int
    {
        $total = 0;
        $intents = Arr::is($state['intents'] ?? null) ? $state['intents'] : [];
        foreach ($intents as $info) {
            if (Arr::is($info['statements'] ?? null)) {
                $statements = (new Collection($info['statements']))->filter(
                    static fn($statement): bool => Val::isNotEmpty(Str::trim((string)$statement))
                );
                $total += $statements->count();
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
            if (Val::isEmpty($value)) {
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
        if (!Arr::is($targets)) {
            $targets = [$targets];
        }

        return self::normalizeStringList($targets);
    }

    protected static function emit(?callable $progress, array $event): void
    {
        if (!Func::isCallable($progress)) {
            return;
        }

        $progress($event);
    }

    protected static function normalize(mixed $value): mixed
    {
        if (!Arr::is($value)) {
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
