<?php

declare(strict_types=1);

namespace App\Services\Bridge\Stripe;

use App\Bridge\Stripe\Actions\ResolveStripeMetadataAction;
use App\Bridge\Stripe\DTOs\ResolvedStripeContext;
use App\Bridge\Stripe\Exceptions\BridgeMetadataException;
use App\Events\Bridge\PaymentConfirmed;
use App\Events\Bridge\ServerReactivated;
use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Jobs\ProvisionServerJob;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Event;

/**
 * Stripe `checkout.session.completed` orchestrator. Two paths :
 *
 *  - resubscribe : `metadata.is_resubscribe=true` + `peregrine_server_id`
 *    → revives the existing server (clear scheduled deletion, unsuspend
 *    Pelican-side, fire `ServerReactivated`).
 *  - normal : delegates to `ResolveStripeMetadataAction` for full
 *    validation (pivot, shop status, configuration existence) then
 *    chains LinkPelicanAccountJob → ProvisionServerJob.
 *
 * On `BridgeMetadataException`, returns a 200-style summary so the
 * controller's audit log captures the rejection without triggering Stripe
 * retries. Admin notifications surface via the audit dashboard.
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
        $metadata = is_array($sessionData['metadata'] ?? null) ? $sessionData['metadata'] : [];

        $isResubscribe = ($metadata['is_resubscribe'] ?? '') === 'true';
        $resubscribeServerId = (int) ($metadata['peregrine_server_id'] ?? 0);
        if ($isResubscribe && $resubscribeServerId > 0) {
            return self::handleResubscribe(
                $event,
                $resubscribeServerId,
                is_string($subscriptionId) ? $subscriptionId : null,
            );
        }

        try {
            $context = (new ResolveStripeMetadataAction())($metadata);
        } catch (BridgeMetadataException $e) {
            Log::warning('Stripe checkout.session.completed metadata rejected', [
                'event_id' => $event->id,
                'reason' => $e->reason,
                'details' => $e->details,
            ]);
            return ['skipped' => $e->reason, 'details' => $e->details];
        }

        $serverName = self::extractServerName($sessionData);

        $user = User::firstOrCreate(
            ['email' => $context->userEmail],
            [
                'name' => $sessionData['customer_details']['name']
                    ?? Str::before($context->userEmail, '@'),
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

        $amountTotal = (int) ($sessionData['amount_total'] ?? 0);
        $currency = (string) ($sessionData['currency'] ?? 'eur');
        $invoiceId = is_string($sessionData['invoice'] ?? null) ? $sessionData['invoice'] : null;
        event(new PaymentConfirmed(
            user: $user,
            configuration: $context->configuration,
            amountCents: $amountTotal,
            currency: $currency,
            invoiceId: $invoiceId,
        ));

        LinkPelicanAccountJob::dispatch($user->id, 'stripe-checkout')
            ->chain([
                new ProvisionServerJob(
                    serverConfigurationId: $context->configuration->id,
                    userId: $user->id,
                    idempotencyKey: $idempotencyKey,
                    serverNameOverride: $serverName,
                    stripeSubscriptionId: is_string($subscriptionId) ? $subscriptionId : null,
                    paymentIntentId: $paymentIntentId !== '' ? $paymentIntentId : null,
                    externalOrderId: $context->externalOrderId,
                ),
            ]);

        return [
            'dispatched' => 'LinkPelicanAccountJob+ProvisionServerJob',
            'configuration_id' => $context->configuration->id,
            'shop_id' => $context->shop->id,
            'user_id' => $user->id,
            'subscription_id' => is_string($subscriptionId) ? $subscriptionId : null,
            'server_name' => $serverName,
            'external_order_id' => $context->externalOrderId,
        ];
    }

    /**
     * Server name override from the optional `server_name` Stripe Checkout
     * custom field. Returns null when absent — `ProvisionServerJob` falls
     * back to the configuration's `name_template`.
     *
     * @param  array<string, mixed>  $sessionData
     */
    private static function extractServerName(array $sessionData): ?string
    {
        $customFields = $sessionData['custom_fields'] ?? [];
        if (! is_array($customFields)) {
            return null;
        }
        foreach ($customFields as $field) {
            if (is_array($field) && strtolower((string) ($field['key'] ?? '')) === 'server_name') {
                $value = $field['text']['value'] ?? null;
                return is_string($value) ? $value : null;
            }
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
