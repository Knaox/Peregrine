<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates incoming Pelican outgoing webhooks.
 *
 * Decoupled from Bridge mode — the receiver is its own feature, gated solely
 * by the `pelican_webhook_enabled` setting (admin enables it from
 * /admin/pelican-webhook-settings). This lets the same webhook be used in
 * shop_stripe mode (install-status updates) and Paymenter mode (full mirror).
 *
 * Pelican does NOT sign its webhook payloads — there is no native HMAC
 * signature header. The only auth available is the freeform "headers" map
 * in /admin/webhooks Pelican-side, where we put a long random bearer token
 * generated in /admin/pelican-webhook-settings.
 *
 * Resolution :
 *   1. `Authorization: Bearer <token>` (preferred — standard scheme)
 *   2. `X-Pelican-Token: <token>`     (fallback — easier to copy-paste)
 *
 * The expected token is read at request time from SettingsService
 * (`pelican_webhook_token`, encrypted via Crypt::encryptString). For installs
 * that haven't run the extract migration yet, `bridge_pelican_webhook_token`
 * is read as a fallback.
 *
 * Status codes :
 *   503 — webhook receiver disabled (admin needs to flip the toggle)
 *   503 — token not configured (admin needs to generate one)
 *   401 — token missing OR mismatch (timing-safe via hash_equals)
 */
class VerifyPelicanWebhookToken
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isEnabled()) {
            $this->logRejection($request, 'webhook_disabled');
            return response()->json(['error' => 'pelican.webhook_disabled'], 503);
        }

        $expected = $this->resolveToken();
        if ($expected === '') {
            $this->logRejection($request, 'token_not_configured');
            return response()->json(['error' => 'pelican.token_not_configured'], 503);
        }

        $provided = $this->extractToken($request);
        if ($provided === '') {
            $this->logRejection($request, 'missing_token');
            return response()->json(['error' => 'pelican.missing_token'], 401);
        }

        if (! hash_equals($expected, $provided)) {
            $this->logRejection($request, 'invalid_token');
            return response()->json(['error' => 'pelican.invalid_token'], 401);
        }

        // Hand the parsed JSON payload to the controller (avoids re-parsing).
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $this->logRejection($request, 'invalid_payload');
            return response()->json(['error' => 'pelican.invalid_payload'], 400);
        }

        $request->attributes->set('pelican.event', $payload);

        return $next($request);
    }

    /**
     * Log middleware-level rejections so admins can debug Pelican-side
     * misconfiguration (wrong token, toggle off, etc.) without having to
     * flip on packet captures. Rejected requests never reach the controller,
     * so they would otherwise leave no trace.
     *
     * Gated on APP_DEBUG to avoid spamming production logs when bots probe
     * the public endpoint. Admins flip APP_DEBUG=true temporarily when
     * troubleshooting a real Pelican misconfiguration.
     */
    private function logRejection(Request $request, string $reason): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::warning('Pelican webhook rejected at middleware', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->header('User-Agent', ''), 0, 120),
            'event_header' => substr((string) $request->header('X-Webhook-Event', ''), 0, 80),
            'has_authorization_header' => $request->header('Authorization') !== null,
        ]);
    }

    private function isEnabled(): bool
    {
        $value = (string) $this->settings->get('pelican_webhook_enabled', 'false');

        return $value === 'true' || $value === '1';
    }

    private function resolveToken(): string
    {
        // Read the new key first, fall back to the legacy Bridge-coupled key
        // for installs that haven't run the extract migration yet.
        $envelope = (string) $this->settings->get('pelican_webhook_token', '');
        if ($envelope === '') {
            $envelope = (string) $this->settings->get('bridge_pelican_webhook_token', '');
        }
        if ($envelope === '') {
            return '';
        }

        try {
            return Crypt::decryptString($envelope);
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractToken(Request $request): string
    {
        $authHeader = (string) $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return (string) $request->header('X-Pelican-Token', '');
    }
}
