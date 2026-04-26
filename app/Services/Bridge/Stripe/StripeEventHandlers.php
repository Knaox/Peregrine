<?php

namespace App\Services\Bridge\Stripe;

use App\Events\Bridge\PaymentConfirmed;
use App\Events\Bridge\ServerReactivated;
use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Jobs\ProvisionServerJob;
use App\Jobs\SubscriptionUpdateJob;
use App\Jobs\SuspendServerJob;
use App\Models\Server;
use App\Models\ServerPlan;
use App\Models\User;
use App\Notifications\Bridge\PaymentFailedNotification;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Stripe\Event;

/**
 * Per-event handlers for the Stripe webhook receiver.
 *
 * Extracted from `StripeWebhookController` to keep the controller focused
 * on idempotency / dispatch routing / response shaping. Each method here
 * takes a parsed `Stripe\Event`, performs the business lookups (find the
 * plan, find / create the user, etc.), and dispatches one of the queued
 * jobs (`ProvisionServerJob`, `SubscriptionUpdateJob`, `SuspendServerJob`).
 *
 * Each handler returns an audit summary array that the controller persists
 * in the `stripe_processed_events` ledger. Returning a `['skipped' => ...]`
 * map signals "no side effect taken" for that delivery (e.g. unknown
 * price_id, missing customer email).
 *
 * No state, no constructor — call statically. Pattern identical to
 * `BridgeSettingsHtmlHelpers` and other extracted helper classes in the
 * codebase.
 */
final class StripeEventHandlers
{
    /**
     * @return array<string, mixed>|null  Summary persisted for audit
     */
    public static function handleCheckoutCompleted(Event $event): ?array
    {
        $session = $event->data->object;
        $sessionData = is_object($session) ? $session->toArray() : (array) $session;

        $paymentIntentId = (string) ($sessionData['payment_intent'] ?? '');
        $subscriptionId = $sessionData['subscription'] ?? null;
        $customerId = $sessionData['customer'] ?? null;
        $customerEmail = $sessionData['customer_details']['email']
            ?? $sessionData['customer_email']
            ?? null;

        // Resubscribe flow : the shop posts metadata.is_resubscribe=true +
        // metadata.peregrine_server_id when the customer is reviving an
        // existing suspended server (instead of provisioning a new one).
        // We branch out before the regular flow so we can REUSE the local
        // row + Pelican server, just with a fresh Stripe subscription.
        $isResubscribe = ($sessionData['metadata']['is_resubscribe'] ?? '') === 'true';
        $resubscribeServerId = (int) ($sessionData['metadata']['peregrine_server_id'] ?? 0);
        if ($isResubscribe && $resubscribeServerId > 0) {
            return self::handleResubscribe($event, $sessionData, $resubscribeServerId, is_string($subscriptionId) ? $subscriptionId : null);
        }

        // Stripe Checkout custom_fields -> first text field whose key is
        // "server_name" (case-insensitive) is the user-supplied server name.
        $customFields = $sessionData['custom_fields'] ?? [];
        $serverName = null;
        foreach ($customFields as $field) {
            if (strtolower((string) ($field['key'] ?? '')) === 'server_name') {
                $serverName = $field['text']['value'] ?? null;
                break;
            }
        }

        // Price ID lookup: try line_items first, then metadata, then call
        // back Stripe with expand[]=line_items. The webhook payload never
        // inlines line_items by default (documented Stripe behavior), so
        // the API expand is the realistic path for any standard Checkout
        // Session that doesn't carry a metadata.stripe_price_id.
        $priceId = null;
        $lineItems = $sessionData['line_items']['data'] ?? [];
        if (! empty($lineItems)) {
            $priceId = $lineItems[0]['price']['id'] ?? null;
        }
        if ($priceId === null && isset($sessionData['metadata']['stripe_price_id'])) {
            $priceId = $sessionData['metadata']['stripe_price_id'];
        }
        if ($priceId === null && ! empty($sessionData['id'])) {
            $priceId = app(StripeSessionFetcher::class)
                ->fetchFirstLineItemPriceId((string) $sessionData['id']);
        }

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

        // Resolve or create the user. firstOrCreate is keyed on email; we
        // also persist stripe_customer_id so future events can find this
        // user by customer ID directly.
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

        // Idempotency for the provisioning. We pick the most stable id we can
        // see, in this order :
        //   1. payment_intent — present on one-shot checkouts
        //   2. subscription   — stable for the entire lifetime of a sub, even
        //      across re-deliveries that mint a new event.id
        //   3. session.id     — stable for the Checkout session itself
        //   4. event.id       — last resort; CHANGES on every Stripe redeliver
        //      so it must never be the only choice for subscription mode
        //      (otherwise each Dashboard "Resend" creates a duplicate Server).
        if ($paymentIntentId !== '') {
            $idempotencyKey = 'stripe-pi-'.$paymentIntentId;
        } elseif (is_string($subscriptionId) && $subscriptionId !== '') {
            $idempotencyKey = 'stripe-sub-'.$subscriptionId;
        } elseif (! empty($sessionData['id'])) {
            $idempotencyKey = 'stripe-cs-'.$sessionData['id'];
        } else {
            $idempotencyKey = 'stripe-event-'.$event->id;
        }

        // Fire the receipt mail right away — independent of the provisioning
        // chain so the customer gets a payment confirmation even if the
        // server provisioning is slow or temporarily failing. Listener is
        // gated to shop_stripe mode.
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

        // Chain: ensure the user has a Pelican account FIRST (which the
        // provision job needs as `pelican_user_id`), THEN provision the
        // server. If the link fails after retries, the chain stops and the
        // server is never marked `provisioning_failed` for the wrong reason.
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
     * Resubscribe flow : the customer paid for a fresh subscription whose
     * Checkout Session metadata flagged a Peregrine server to revive
     * (rather than a brand-new one to provision). We re-attach the new
     * subscription, clear the scheduled deletion, unsuspend in Pelican,
     * and fire ServerReactivated.
     *
     * Security : the link in the suspended-server email is HMAC-signed
     * with bridge_shop_shared_secret — the shop is REQUIRED to verify
     * the signature before posting these metadata. We trust the shop to
     * have done it (same pattern as the rest of the Bridge HTTP API).
     *
     * @param  array<string, mixed>  $sessionData
     * @return array<string, mixed>
     */
    private static function handleResubscribe(
        Event $event,
        array $sessionData,
        int $serverId,
        ?string $newSubscriptionId,
    ): array {
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

        // Best-effort unsuspend on the Pelican side. If the Pelican server
        // was already unsuspended (e.g. admin did it manually) the call is
        // a no-op. If Pelican is unreachable we still keep the local state
        // change — the admin can manually unsuspend, and the user gets the
        // celebratory mail anyway since the subscription is real.
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

    /**
     * `customer.subscription.updated` : either a plan change (price_id
     * different in items) OR a status transition (active→past_due, etc.).
     * We dispatch SubscriptionUpdateJob unconditionally — it short-circuits
     * if nothing actionable changed. Stripe sends this event for many
     * non-meaningful reasons (trial flag flip, default payment method
     * update…) so the cheap-skip branch lives in the job, not here.
     *
     * @return array<string, mixed>
     */
    public static function handleSubscriptionUpdated(Event $event): array
    {
        $sub = $event->data->object;
        $subData = is_object($sub) ? $sub->toArray() : (array) $sub;

        $subscriptionId = (string) ($subData['id'] ?? '');
        $newStatus = (string) ($subData['status'] ?? '');
        // First item's price (most subscriptions are single-item).
        $newPriceId = $subData['items']['data'][0]['price']['id'] ?? null;

        if ($subscriptionId === '') {
            return ['skipped' => 'missing_subscription_id'];
        }

        SubscriptionUpdateJob::dispatch(
            eventId: $event->id,
            stripeSubscriptionId: $subscriptionId,
            newStripePriceId: is_string($newPriceId) ? $newPriceId : null,
            newStatus: $newStatus,
        );

        return [
            'dispatched' => 'SubscriptionUpdateJob',
            'subscription_id' => $subscriptionId,
            'new_price_id' => $newPriceId,
            'new_status' => $newStatus,
        ];
    }

    /**
     * `customer.subscription.deleted` : the subscription is over for good
     * (cancelled by client, by admin, or by Stripe after exhausting dunning
     * retries). Suspend immediately + schedule deletion at end of grace
     * period. Admin can cancel the deletion via Filament action.
     *
     * @return array<string, mixed>
     */
    public static function handleSubscriptionDeleted(Event $event): array
    {
        $sub = $event->data->object;
        $subData = is_object($sub) ? $sub->toArray() : (array) $sub;

        $subscriptionId = (string) ($subData['id'] ?? '');
        if ($subscriptionId === '') {
            return ['skipped' => 'missing_subscription_id'];
        }

        SuspendServerJob::dispatch(
            eventId: $event->id,
            stripeSubscriptionId: $subscriptionId,
            scheduleDeletion: true,
        );

        return [
            'dispatched' => 'SuspendServerJob',
            'subscription_id' => $subscriptionId,
            'schedule_deletion' => true,
        ];
    }

    /**
     * `invoice.payment_failed` : informational. Stripe handles the dunning
     * retry policy itself and emits subsequent `subscription.updated`
     * events as the status drifts (active → past_due → unpaid → cancelled).
     * We notify admins so they can reach out to high-value customers, but
     * don't suspend the server here — that's the subscription event's job.
     *
     * @return array<string, mixed>
     */
    public static function handlePaymentFailed(Event $event): array
    {
        $invoice = $event->data->object;
        $invoiceData = is_object($invoice) ? $invoice->toArray() : (array) $invoice;

        $admins = User::where('is_admin', true)->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new PaymentFailedNotification(
                stripeInvoiceId: (string) ($invoiceData['id'] ?? 'unknown'),
                stripeSubscriptionId: $invoiceData['subscription'] ?? null,
                customerEmail: $invoiceData['customer_email'] ?? null,
                amountCents: (int) ($invoiceData['amount_due'] ?? 0),
                currency: strtoupper((string) ($invoiceData['currency'] ?? 'usd')),
                nextAttemptAt: isset($invoiceData['next_payment_attempt'])
                    ? date('Y-m-d H:i:s', (int) $invoiceData['next_payment_attempt'])
                    : null,
            ));
        }

        return [
            'notified_admins' => $admins->count(),
            'invoice_id' => $invoiceData['id'] ?? null,
            'amount_due' => $invoiceData['amount_due'] ?? null,
        ];
    }

    /**
     * `invoice.paid` : a recurring renewal succeeded (or a trial converted
     * to a paid first charge). Stripe handles the receipt itself, so we
     * don't email the customer — the value here is auditing the renewal in
     * `stripe_processed_events` and clearing any stale `provisioning_error`
     * left over from a previous transient failure (the renewal succeeded,
     * any past failure is no longer relevant to display).
     *
     * @return array<string, mixed>
     */
    public static function handleInvoicePaid(Event $event): array
    {
        $invoice = $event->data->object;
        $invoiceData = is_object($invoice) ? $invoice->toArray() : (array) $invoice;

        $subscriptionId = isset($invoiceData['subscription']) && is_string($invoiceData['subscription'])
            ? $invoiceData['subscription']
            : null;

        if ($subscriptionId === null) {
            // One-shot invoice (no subscription) — nothing to reconcile here.
            return ['skipped' => 'no_subscription_on_invoice', 'invoice_id' => $invoiceData['id'] ?? null];
        }

        $server = Server::where('stripe_subscription_id', $subscriptionId)->first();
        if ($server === null) {
            return ['skipped' => 'server_not_found', 'subscription_id' => $subscriptionId];
        }

        $cleared = false;
        if ($server->provisioning_error !== null) {
            $server->forceFill(['provisioning_error' => null])->save();
            $cleared = true;
        }

        return [
            'audited' => true,
            'subscription_id' => $subscriptionId,
            'invoice_id' => $invoiceData['id'] ?? null,
            'amount_paid' => $invoiceData['amount_paid'] ?? null,
            'provisioning_error_cleared' => $cleared,
        ];
    }

    /**
     * `customer.subscription.trial_will_end` : Stripe sends this 3 days
     * before the trial converts to paid. We use it to email the customer
     * a reminder ("your trial ends on X, your card will be charged"). No
     * server side-effect — this is a pure notification.
     *
     * @return array<string, mixed>
     */
    public static function handleTrialWillEnd(Event $event): array
    {
        $sub = $event->data->object;
        $subData = is_object($sub) ? $sub->toArray() : (array) $sub;

        $subscriptionId = (string) ($subData['id'] ?? '');
        $trialEnd = isset($subData['trial_end']) ? (int) $subData['trial_end'] : null;

        if ($subscriptionId === '') {
            return ['skipped' => 'missing_subscription_id'];
        }

        $server = Server::where('stripe_subscription_id', $subscriptionId)->first();
        if ($server === null) {
            return ['skipped' => 'server_not_found', 'subscription_id' => $subscriptionId];
        }

        $user = $server->user;
        if ($user === null) {
            return ['skipped' => 'user_not_found', 'server_id' => $server->id];
        }

        event(new \App\Events\Bridge\TrialWillEnd(
            user: $user,
            server: $server,
            trialEndsAt: $trialEnd !== null ? \Carbon\CarbonImmutable::createFromTimestamp($trialEnd) : \Carbon\CarbonImmutable::now()->addDays(3),
        ));

        return [
            'dispatched' => 'TrialWillEnd',
            'subscription_id' => $subscriptionId,
            'server_id' => $server->id,
            'user_id' => $user->id,
            'trial_end' => $trialEnd,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function handleUnsupported(Event $event): array
    {
        return ['ignored' => 'unsupported_event_type', 'type' => $event->type];
    }
}
