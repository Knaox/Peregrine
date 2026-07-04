<?php

declare(strict_types=1);

namespace App\Services\Pelican;

use Illuminate\Support\Facades\Cache;

/**
 * Generation-versioned cache for a server's schedule list.
 *
 * `Cache::forget()` alone is race-prone here: an index() read that started
 * BEFORE a mutation can finish AFTER the forget and re-seed the cache with the
 * pre-mutation snapshot for another 5 minutes — which is exactly how a freshly
 * created schedule "never shows up" until a lucky manual refresh. Bumping a
 * generation counter instead makes any in-flight stale writer write into a key
 * nobody reads any more, while readers immediately see the new generation.
 */
final class ScheduleCache
{
    private const TTL = 300;

    /**
     * @param  callable(): array<int, array<string, mixed>>  $resolver
     * @return array<int, array<string, mixed>>
     */
    public static function remember(string $identifier, callable $resolver): array
    {
        return Cache::remember(self::key($identifier), self::TTL, $resolver);
    }

    /** Invalidate by moving every reader to a fresh generation. */
    public static function bust(string $identifier): void
    {
        Cache::increment(self::versionKey($identifier));
    }

    private static function key(string $identifier): string
    {
        $version = (int) Cache::rememberForever(self::versionKey($identifier), fn (): int => 1);

        return "server_schedules:{$identifier}:v{$version}";
    }

    private static function versionKey(string $identifier): string
    {
        return "server_schedules_ver:{$identifier}";
    }
}
