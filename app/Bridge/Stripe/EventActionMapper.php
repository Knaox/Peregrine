<?php

declare(strict_types=1);

namespace App\Bridge\Stripe;

use App\Bridge\Stripe\Actions\HandleDisputeAction;
use App\Bridge\Stripe\Actions\HandleRefundAction;
use App\Services\Bridge\Stripe\StripeCheckoutHandler;
use App\Services\Bridge\Stripe\StripeEventHandlers;
use Stripe\Event;

/**
 * Single dispatch table for inbound Stripe events. Adding a new event
 * type means adding one match arm here ; controllers and tests target
 * this single class instead of the legacy ad-hoc routing in
 * `StripeWebhookController` / `StripeEventHandlers`.
 *
 * Returns the same audit summary array as the underlying handler so the
 * controller's idempotency ledger insert stays unchanged.
 */
final class EventActionMapper
{
    /**
     * @return array<string, mixed>|null
     */
    public static function dispatch(Event $event): ?array
    {
        return match ($event->type) {
            'checkout.session.completed' => StripeCheckoutHandler::handle($event),
            'customer.subscription.deleted' => StripeEventHandlers::handleSubscriptionDeleted($event),
            'customer.subscription.updated' => StripeEventHandlers::handleSubscriptionUpdated($event),
            'customer.subscription.trial_will_end' => StripeEventHandlers::handleTrialWillEnd($event),
            'invoice.paid' => StripeEventHandlers::handleInvoicePaid($event),
            'invoice.payment_failed' => StripeEventHandlers::handlePaymentFailed($event),
            'charge.refunded' => HandleRefundAction::handle($event),
            'charge.dispute.created' => HandleDisputeAction::handle($event),
            default => StripeEventHandlers::handleUnsupported($event),
        };
    }
}
