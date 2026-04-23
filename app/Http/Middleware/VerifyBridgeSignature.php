<?php

namespace App\Http\Middleware;

use App\Services\Bridge\BridgeModeService;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates HMAC-SHA256 signatures on incoming Bridge API requests from
 * the Shop. Same security model as Stripe webhooks (signed payload + 5-min
 * replay window via timestamp).
 *
 * Headers expected on every request:
 *   X-Bridge-Signature: sha256=<hex digest>
 *   X-Bridge-Timestamp: <unix milliseconds>
 *
 * Secret is read at request time from SettingsService (encrypted at rest
 * via Crypt::encryptString — same pattern as AuthProviderRegistry uses for
 * OAuth client_secrets). This means rotating the secret takes effect on
 * the very next request without any cache invalidation.
 *
 * Status codes:
 *   503 — Bridge disabled OR secret not configured
 *   401 — Signature missing/invalid (timing-safe via hash_equals)
 *   410 — Timestamp outside the 5-minute replay window
 */
class VerifyBridgeSignature
{
    /** Replay protection window — accept timestamps within ±5 min of server time. */
    private const REPLAY_WINDOW_MS = 5 * 60 * 1000;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly BridgeModeService $bridgeMode,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->bridgeMode->isShopStripe()) {
            return response()->json(['error' => 'bridge.disabled'], 503);
        }

        $secret = $this->resolveSecret();
        if ($secret === '') {
            return response()->json(['error' => 'bridge.secret_not_configured'], 503);
        }

        $timestampHeader = $request->header('X-Bridge-Timestamp');
        if ($timestampHeader === null || ! ctype_digit((string) $timestampHeader)) {
            return response()->json(['error' => 'bridge.invalid_timestamp'], 410);
        }

        $timestamp = (int) $timestampHeader;
        $now = (int) (microtime(true) * 1000);
        if (abs($now - $timestamp) > self::REPLAY_WINDOW_MS) {
            return response()->json(['error' => 'bridge.timestamp_expired'], 410);
        }

        $signature = (string) $request->header('X-Bridge-Signature', '');
        if ($signature === '' || ! str_starts_with($signature, 'sha256=')) {
            return response()->json(['error' => 'bridge.invalid_signature'], 401);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'bridge.invalid_signature'], 401);
        }

        // Tag the request so the controller can read signature_valid for audit.
        $request->attributes->set('bridge.signature_valid', true);

        return $next($request);
    }

    private function resolveSecret(): string
    {
        $envelope = (string) $this->settings->get('bridge_shop_shared_secret', '');
        if ($envelope === '') {
            return '';
        }

        try {
            return Crypt::decryptString($envelope);
        } catch (\Throwable) {
            return '';
        }
    }
}
