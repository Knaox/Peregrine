<?php

namespace App\Services\Bridge\Stripe;

use App\Events\Bridge\PaymentConfirmed;
use App\Events\Bridge\ServerReactivated;
use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Jobs\ProvisionServerJob;
use App\Models\Server;
use App\Models\ServerPlan;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Event;

/**
 * Stripe `checkout.session.completed` handler. Extracted from
 * StripeEventHandlers to keep that file under the 300-line plafond
 * CLAUDE.md.
 *
 * Two paths :
 *  - resubscribe : metadata.is_resubscribe=true + peregrine_server_id →
 *    revive the existing server (clear scheduled deletion, unsuspend
 *    Pelican-side, fire ServerReactivated)
 *  - normal     : provision a new server via the
 *    LinkPelicanAccountJob → ProvisionServerJob chain
 */
final class StripeCheckoutHandler
{
    /**
     * @return array<string, mixed>|null  audit summary
     */
    public static function handle(Event $event): ?array
    {
        $session = $event->data->object;
        $sessionData = is_object($session) ? $session->toArray() : (array) $session;

        $paymentIntentId = (string) ($sessionData['payment_intent'] ?? '');
        $subscriptionId = $sessionData['subscription'] ?? null;
        $customerId = $sessionData['customer'] ?? null;
        $customerEmail = $sessionData['customer_details']['email']
            ?? $sessionData['customer_email']
            ?? null;

        $isResubscribe = ($sessionData['metadata']['is_resubscribe'] ?? '') === 'true';
        $resubscribeServerId = (int) ($sessionData['metadata']['peregrine_server_id'] ?? 0);
        if ($isResubscribe && $resubscribeServerId > 0) {
            return self::handleResubscribe(
                $event,
                $resubscribeServerId,
                is_string($subscriptionId) ? $subscriptionId : null,
            );
        }

        // Server name from Checkout custom field "server_name".
        $customFields = $sessionData['custom_fields'] ?? [];
        $serverName = null;
        foreach ($customFields as $field) {
            if (strtolower((string) ($field['key'] ?? '')) === 'server_name') {
                $serverName = $field['text']['value'] ?? null;
                break;
            }
        }

        $priceId = self::resolvePriceId($sessionData);
        if ($priceId === null) {
            Log::warning('Stripe checkout.session.completed without resolvable price_id', [
                'event_id' => $event->id,
                'session_id' => $sessionData['id'] ?? null,
            ]);
            return ['skipped' => 'no_price_id'];
        }

        $plan = ServerPlan::where('stripe_price_id', $priceId)->first();
        if ($plan === null) {
            Log::warning('Stripe checkout.session.completed with unknown stripe_price_id', [
                'event_id' => $event->id,
                'price_id' => $priceId,
            ]);
            return ['skipped' => 'unknown_price_id', 'price_id' => $priceId];
        }

        if ($customerEmail === null) {
            Log::warning('Stripe checkout.session.completed missing customer_email', [
                'event_id' => $event->id,
            ]);
            return ['skipped' => 'no_customer_email'];
        }

        $user = User::firstOrCreate(
            ['email' => strtolower(trim($customerEmail))],
            [
                'name' => $sessionData['customer_details']['name'] ?? Str::before($customerEmail, '@'),
                'locale' => app(SettingsService::class)->get('default_locale', 'en'),
            ],
        );
        if ($customerId !== null && $user->stripe_customer_id !== $customerId) {
            $user->forceFill(['stripe_customer_id' => $customerId])->save();
        }

        $idempotencyKey = self::buildIdempotencyKey(
            $paymentIntentId,
            is_string($subscriptionId) ? $subscriptionId : null,
            (string) ($sessionData['id'] ?? ''),
            $event->id,
        );

        // Receipt email — independent of provisioning chain so payment
        // confirmation lands even if provisioning is slow.
        $amountTotal = (int) ($sessionData['amount_total'] ?? 0);
        $currency = (string) ($sessionData['currency'] ?? 'eur');
        $invoiceId = is_string($sessionData['invoice'] ?? null) ? $sessionData['invoice'] : null;
        event(new PaymentConfirmed(
            user: $user,
            plan: $plan,
            amountCents: $amountTotal,
            currency: $currency,
            invoiceId: $invoiceId,
        ));

        LinkPelicanAccountJob::dispatch($user->id, 'stripe-checkout')
            ->chain([
                new ProvisionServerJob(
                    planId: $plan->id,
                    userId: $user->id,
                    idempotencyKey: $idempotencyKey,
                    serverNameOverride: $serverName,
                    stripeSubscriptionId: is_string($subscriptionId) ? $subscriptionId : null,
                    paymentIntentId: $paymentIntentId !== '' ? $paymentIntentId : null,
                ),
            ]);

        return [
            'dispatched' => 'LinkPelicanAccountJob+ProvisionServerJob',
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'subscription_id' => is_string($subscriptionId) ? $subscriptionId : null,
            'server_name' => $serverName,
        ];
    }

    /**
     * @param  array<string, mixed>  $sessionData
     */
    private static function resolvePriceId(array $sessionData): ?string
    {
        $lineItems = $sessionData['line_items']['data'] ?? [];
        if (! empty($lineItems)) {
            return $lineItems[0]['price']['id'] ?? null;
        }
        if (isset($sessionData['metadata']['stripe_price_id'])) {
            return $sessionData['metadata']['stripe_price_id'];
        }
        if (! empty($sessionData['id'])) {
            return app(StripeSessionFetcher::class)
                ->fetchFirstLineItemPriceId((string) $sessionData['id']);
        }
        return null;
    }

    private static function buildIdempotencyKey(string $paymentIntentId, ?string $subscriptionId, string $sessionId, string $eventId): string
    {
        if ($paymentIntentId !== '') {
            return 'stripe-pi-'.$paymentIntentId;
        }
        if ($subscriptionId !== null && $subscriptionId !== '') {
            return 'stripe-sub-'.$subscriptionId;
        }
        if ($sessionId !== '') {
            return 'stripe-cs-'.$sessionId;
        }
        return 'stripe-event-'.$eventId;
    }

    /**
     * @return array<string, mixed>
     */
    private static function handleResubscribe(Event $event, int $serverId, ?string $newSubscriptionId): array
    {
        $server = Server::find($serverId);
        if ($server === null) {
            Log::warning('Stripe resubscribe : Peregrine server not found', [
                'event_id' => $event->id,
                'peregrine_server_id' => $serverId,
            ]);
            return ['skipped' => 'resubscribe_server_not_found', 'peregrine_server_id' => $serverId];
        }

        $oldSubscriptionId = $server->stripe_subscription_id;
        $server->forceFill([
            'stripe_subscription_id' => $newSubscriptionId,
            'status' => 'active',
            'scheduled_deletion_at' => null,
            'provisioning_error' => null,
        ])->save();

        if ($server->pelican_server_id !== null) {
            try {
                app(PelicanApplicationService::class)->unsuspendServer((int) $server->pelican_server_id);
            } catch (\Throwable $e) {
                Log::warning('Stripe resubscribe : Pelican unsuspend failed (non-blocking)', [
                    'server_id' => $server->id,
                    'pelican_server_id' => $server->pelican_server_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($server->user !== null) {
            event(new ServerReactivated($server->fresh(), $server->user));
        }

        return [
            'reactivated' => true,
            'server_id' => $server->id,
            'old_subscription_id' => $oldSubscriptionId,
            'new_subscription_id' => $newSubscriptionId,
        ];
    }
}
