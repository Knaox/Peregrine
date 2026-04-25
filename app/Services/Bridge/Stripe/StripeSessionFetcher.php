<?php

namespace App\Services\Bridge\Stripe;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * Thin wrapper around Stripe SDK to fetch an expanded Checkout Session.
 *
 * Stripe never includes `line_items` in the `checkout.session.completed`
 * webhook payload (documented behavior, not a bug). To resolve the price_id
 * we must call back the API with `expand[]=line_items`.
 *
 * Lives behind a class so tests can swap it via the container without
 * stubbing the SDK's nested property accessors.
 */
class StripeSessionFetcher
{
    /**
     * Resolve the first line item's price_id for the given Checkout Session.
     * Returns null on any failure (network, auth, deleted session…), the
     * caller logs + records a `skipped` summary in the ledger.
     */
    public function fetchFirstLineItemPriceId(string $sessionId): ?string
    {
        $secret = $this->resolveApiSecret();
        if ($secret === '') {
            Log::warning('Stripe API secret not configured (settings.bridge_stripe_api_secret or env STRIPE_SECRET); cannot expand line_items', [
                'session_id' => $sessionId,
            ]);
            return null;
        }

        try {
            $client = new StripeClient($secret);
            $session = $client->checkout->sessions->retrieve(
                $sessionId,
                ['expand' => ['line_items']],
            );
            return $session->line_items->data[0]->price->id ?? null;
        } catch (\Throwable $e) {
            Log::warning('Stripe checkout.session expand failed', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Setting takes precedence (admin-editable, encrypted in DB), env is the
     * fallback for installs that pre-date the BridgeSettings field.
     */
    private function resolveApiSecret(): string
    {
        $envelope = (string) app(SettingsService::class)->get('bridge_stripe_api_secret', '');
        if ($envelope !== '') {
            try {
                return (string) Crypt::decryptString($envelope);
            } catch (\Throwable $e) {
                Log::warning('Failed to decrypt bridge_stripe_api_secret; falling back to env', [
                    'message' => $e->getMessage(),
                ]);
            }
        }
        return (string) config('services.stripe.secret');
    }
}
