<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the Stripe-Signature header on incoming /api/stripe/webhook
 * requests via the official Stripe SDK (`Stripe\Webhook::constructEvent`).
 * The SDK handles :
 *   - Parsing `t=…,v1=…,v0=…` header
 *   - Timing-safe HMAC-SHA256 comparison
 *   - 300-second replay window (default tolerance)
 *
 * Secret resolution order :
 *   1. SettingsService `bridge_stripe_webhook_secret` (encrypted via Crypt
 *      in the BridgeSettings Filament page) — primary, rotatable without
 *      redeploy
 *   2. `config('bridge.stripe.webhook_secret')` from .env — fallback for
 *      dev/CI environments where SettingsService isn't seeded
 *
 * On success, parses the event payload into a Stripe\Event object and
 * stuffs it into `$request->attributes['stripe.event']` so the controller
 * can read it without re-parsing.
 *
 * Status codes :
 *   401 — invalid signature OR replay-protection failure (Stripe stops retrying)
 *   400 — malformed JSON payload (Stripe stops retrying)
 *   503 — webhook secret not configured (Bridge admin needs to fix)
 */
class VerifyStripeSignature
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->resolveSecret();
        if ($secret === '') {
            return response()->json(['error' => 'stripe.secret_not_configured'], 503);
        }

        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature', '');

        if ($sigHeader === '') {
            return response()->json(['error' => 'stripe.missing_signature'], 401);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'stripe.invalid_signature'], 401);
        } catch (UnexpectedValueException $e) {
            return response()->json(['error' => 'stripe.invalid_payload'], 400);
        }

        // Hand the parsed event to the controller (avoids double-parsing).
        $request->attributes->set('stripe.event', $event);

        return $next($request);
    }

    private function resolveSecret(): string
    {
        $envelope = (string) $this->settings->get('bridge_stripe_webhook_secret', '');
        if ($envelope !== '') {
            try {
                return Crypt::decryptString($envelope);
            } catch (\Throwable) {
                // Decryption failure (key rotated, corruption…) — fall through
                // to env fallback rather than locking out the webhook.
            }
        }

        return (string) config('bridge.stripe.webhook_secret', '');
    }
}
