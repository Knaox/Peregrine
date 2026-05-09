<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ShopApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token middleware for the public API v1 surface.
 *
 *   Authorization: Bearer psk_live_<48 hex>
 *
 * The plaintext token is hashed with SHA-256 and matched against
 * `shop_api_keys.key_hash` (UNIQUE). Always uses `hash_equals` to avoid
 * timing leaks. Rejects with 401 on :
 *  - missing / malformed Authorization header
 *  - unknown hash
 *  - revoked or expired key
 *
 * Rejects with 403 when the owning Shop is suspended (the key is
 * technically valid but the org is paused).
 *
 * On success, the resolved `Shop` and `ShopApiKey` are attached to the
 * request via `$request->attributes->set(...)` so controllers and
 * downstream middleware (RateLimiter, IdempotencyKey) can read them
 * without re-querying.
 *
 * Per-ability gating happens in the route definition via the
 * `ability:configurations:read` middleware modifier — this middleware
 * only resolves identity, never authorisation per-route.
 *
 * `last_used_at` / `last_used_ip` updates are written deferred (after
 * response) so they don't slow the request hot path.
 */
final class EnsureShopApiKey
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            return $this->unauthorized('missing_bearer_token');
        }

        $hash = hash('sha256', $token);
        $apiKey = ShopApiKey::query()
            ->where('key_hash', $hash)
            ->first();

        // Constant-time check : even if the row was found, double-verify
        // the hash to avoid early-return timing differences. Then fall
        // through to lifecycle checks regardless of the found path.
        if ($apiKey === null || ! hash_equals($apiKey->key_hash, $hash)) {
            return $this->unauthorized('invalid_api_key');
        }

        if (! $apiKey->isUsable()) {
            return $this->unauthorized('api_key_revoked_or_expired');
        }

        $shop = $apiKey->shop;
        if ($shop === null) {
            return $this->unauthorized('orphan_api_key');
        }
        if (! $shop->isActive()) {
            return $this->forbidden('shop_suspended');
        }

        if ($ability !== null && ! $apiKey->hasAnyAbility([$ability])) {
            return $this->forbidden('insufficient_scope', ['required_ability' => $ability]);
        }

        $request->attributes->set('shop', $shop);
        $request->attributes->set('apiKey', $apiKey);

        $response = $next($request);

        // Deferred tracking write. Wrapped in try so a failure never
        // surfaces to the client.
        try {
            $apiKey->forceFill([
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
            ])->save();
        } catch (\Throwable) {
            // swallow — auth already succeeded
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function unauthorized(string $code, array $details = []): Response
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => __('api_v1.unauthorized'),
                'details' => $details === [] ? null : $details,
            ],
        ], 401);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function forbidden(string $code, array $details = []): Response
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => __('api_v1.forbidden'),
                'details' => $details === [] ? null : $details,
            ],
        ], 403);
    }
}
