<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Holds the "password verified, awaiting TOTP" state between a successful
 * password (or OAuth) validation and the 2FA code challenge. Backed by Redis
 * (SPA cross-tab safe) with a 5-minute TTL. Plan §S3.
 *
 * Callers store only the user_id + context — never a session cookie, never
 * the secret or codes. The challenge_id is opaque to the frontend.
 */
class TwoFactorChallengeStore
{
    private const TTL_SECONDS = 300;

    private const KEY_PREFIX = '2fa_pending:';

    /**
     * @param  array{type: string, provider?: string|null, intended_url?: string|null}  $providerContext
     */
    public function put(int $userId, array $providerContext): string
    {
        $challengeId = (string) Str::uuid();

        Cache::put(
            self::KEY_PREFIX.$challengeId,
            [
                'user_id' => $userId,
                'created_at' => now()->toIso8601String(),
                'provider_context' => $providerContext,
            ],
            self::TTL_SECONDS,
        );

        return $challengeId;
    }

    /**
     * @return array{user_id: int, created_at: string, provider_context: array<string, mixed>}|null
     */
    public function get(string $challengeId): ?array
    {
        $raw = Cache::get(self::KEY_PREFIX.$challengeId);

        if (! is_array($raw) || ! isset($raw['user_id'])) {
            return null;
        }

        /** @var array{user_id: int, created_at: string, provider_context: array<string, mixed>} $raw */
        return $raw;
    }

    public function purge(string $challengeId): void
    {
        Cache::forget(self::KEY_PREFIX.$challengeId);
    }
}
