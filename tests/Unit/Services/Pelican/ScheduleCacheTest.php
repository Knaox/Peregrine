<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pelican;

use App\Services\Pelican\ScheduleCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ScheduleCacheTest extends TestCase
{
    public function test_remember_caches_and_serves_the_resolver_result(): void
    {
        $calls = 0;
        $resolver = function () use (&$calls): array {
            $calls++;

            return [['id' => 1]];
        };

        self::assertSame([['id' => 1]], ScheduleCache::remember('srv', $resolver));
        self::assertSame([['id' => 1]], ScheduleCache::remember('srv', $resolver));
        self::assertSame(1, $calls);
    }

    public function test_bust_moves_readers_to_a_fresh_generation(): void
    {
        ScheduleCache::remember('srv', fn (): array => [['id' => 1]]);

        ScheduleCache::bust('srv');

        self::assertSame([['id' => 2]], ScheduleCache::remember('srv', fn (): array => [['id' => 2]]));
    }

    public function test_a_stale_in_flight_writer_cannot_poison_the_new_generation(): void
    {
        // A read started BEFORE the mutation… (its generation key is resolved now)
        ScheduleCache::remember('srv', fn (): array => [['id' => 1, 'tasks' => []]]);

        // …the mutation lands and busts…
        ScheduleCache::bust('srv');

        // …then the stale writer finishes: with forget()-based invalidation it
        // would re-seed the same key for 5 more minutes. Here it writes into
        // the retired generation, which nobody reads any more.
        Cache::put('server_schedules:srv:v1', [['id' => 1, 'tasks' => []]], 300);

        self::assertSame(
            [['id' => 1, 'tasks' => [['id' => 9]]]],
            ScheduleCache::remember('srv', fn (): array => [['id' => 1, 'tasks' => [['id' => 9]]]]),
        );
    }
}
