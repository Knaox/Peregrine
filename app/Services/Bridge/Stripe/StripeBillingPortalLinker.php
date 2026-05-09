<?php

namespace App\Services\Bridge\Stripe;

use App\Models\Server;
use App\Models\ServerConfiguration;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * Builds the URL the customer clicks in the "server suspended" email to
 * reactivate their subscription.
 *
 * Resolution order, best-to-worst customer experience:
 *
 *   1. Stripe Billing Portal session (per-customer, magic link, no email
 *      typed by the user). Requires both `services.stripe.secret` /
 *      `bridge_stripe_api_secret` AND a valid `users.stripe_customer_id`.
 *
 *   2. Static Login link configured in the admin (`bridge_stripe_billing_portal_url`).
 *      User lands on a page that asks for their email, then receives a magic
 *      link by mail. Less seamless but works without an API call.
 *
 *   3. null — the email omits the "Reactivate" button entirely. Caller
 *      decides what to render (typically just the panel link).
 */
class StripeBillingPortalLinker
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function urlFor(User $user, ?string $returnUrl = null): ?string
    {
        $sessionUrl = $this->createSessionUrl($user, $returnUrl);
        if ($sessionUrl !== null) {
            return $sessionUrl;
        }
        $fallback = (string) $this->settings->get('bridge_stripe_billing_portal_url', '');
        return $fallback !== '' ? $fallback : null;
    }

    /**
     * URL the customer clicks to RESUBSCRIBE after a hard cancellation.
     *
     * Stripe does not let a `canceled` subscription be re-activated from
     * the Customer Portal (only `cancel_at_period_end` ones are recoverable
     * there), so the suspended-server email must send the user back to a
     * fresh checkout flow on the shop instead.
     *
     * Reads `bridge_resubscribe_url` setting (admin-editable template) and
     * interpolates the following placeholders :
     *   {server_id}        = local Peregrine Server::id
     *   {configuration_id} = ServerConfiguration::id (= the shop's mirror peregrine_id)
     *   {configuration}    = ServerConfiguration::internal_name (kept for legacy
     *                        templates ; new shops should use configuration_id)
     *   {ts}               = unix timestamp at link generation
     *   {signature}        = HMAC-SHA256 over "{server_id}|{configuration_id}|{ts}",
     *                        keyed with bridge_shop_shared_secret
     *
     * The signature porte sur configuration_id (stable Peregrine ID) au lieu
     * de l'internal_name : ça permet au shop de retrouver son Plan via la FK
     * `peregrine_configuration_id` sans dépendre de la convention historique
     * `Plan::slug == ServerConfiguration::internal_name`.
     *
     * Returns null when no template configured.
     */
    public function resubscribeUrlFor(?Server $server, ?ServerConfiguration $configuration): ?string
    {
        $template = (string) $this->settings->get('bridge_resubscribe_url', '');
        if ($template === '') {
            return null;
        }
        $serverId = (string) ($server?->id ?? '');
        $configurationId = (string) ($configuration?->id ?? '');
        $configurationSlug = (string) ($configuration?->internal_name ?? '');
        $ts = (string) time();
        $signature = $this->signResubscribePayload($serverId, $configurationId, $ts);

        return strtr($template, [
            '{server_id}' => $serverId,
            '{configuration_id}' => $configurationId,
            '{configuration}' => $configurationSlug, // legacy placeholder ; signature no longer covers it
            '{ts}' => $ts,
            '{signature}' => $signature,
        ]);
    }

    private function signResubscribePayload(string $serverId, string $configurationId, string $ts): string
    {
        $secret = $this->resolveShopSharedSecret();
        if ($secret === '') {
            return '';
        }
        return hash_hmac('sha256', "{$serverId}|{$configurationId}|{$ts}", $secret);
    }

    private function resolveShopSharedSecret(): string
    {
        $envelope = (string) $this->settings->get('bridge_shop_shared_secret', '');
        if ($envelope === '') {
            return '';
        }
        try {
            return (string) Crypt::decryptString($envelope);
        } catch (\Throwable) {
            return '';
        }
    }

    private function createSessionUrl(User $user, ?string $returnUrl): ?string
    {
        if ($user->stripe_customer_id === null || $user->stripe_customer_id === '') {
            return null;
        }
        $apiKey = $this->resolveApiSecret();
        if ($apiKey === '') {
            return null;
        }
        try {
            $client = new StripeClient($apiKey);
            $session = $client->billingPortal->sessions->create([
                'customer' => $user->stripe_customer_id,
                'return_url' => $returnUrl ?? rtrim((string) config('app.url', ''), '/').'/dashboard',
            ]);
            return (string) $session->url;
        } catch (\Throwable $e) {
            Log::warning('Stripe BillingPortal session creation failed', [
                'user_id' => $user->id,
                'stripe_customer_id' => $user->stripe_customer_id,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function resolveApiSecret(): string
    {
        $envelope = (string) $this->settings->get('bridge_stripe_api_secret', '');
        if ($envelope !== '') {
            try {
                return (string) Crypt::decryptString($envelope);
            } catch (\Throwable) {
                // fall through to env
            }
        }
        return (string) config('services.stripe.secret');
    }
}
