<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Services;

use Illuminate\Support\Facades\Cache;

/**
 * One-shot store for signon tokens. A launch writes the database credentials
 * under a random token with a short TTL; phpMyAdmin's SignonScript redeems it
 * exactly once. `Cache::pull` is an atomic get-and-delete, so a replayed token
 * resolves to null. The token is hashed at rest, so a cache dump never leaks a
 * live token. Store-agnostic (works on Redis or the default cache store).
 */
class PmaTokenStore
{
    private const PREFIX = 'pma:signon:';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(string $token, array $payload, int $ttl): void
    {
        Cache::put(self::key($token), $payload, max(5, $ttl));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pull(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        /** @var array<string, mixed>|null $data */
        $data = Cache::pull(self::key($token));

        return $data;
    }

    private static function key(string $token): string
    {
        return self::PREFIX.hash('sha256', $token);
    }
}
