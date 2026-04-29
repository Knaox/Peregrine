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
        return StripeCheckoutHandler::handle($event);
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
