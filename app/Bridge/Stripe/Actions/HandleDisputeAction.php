<?php

declare(strict_types=1);

namespace App\Bridge\Stripe\Actions;

use App\Jobs\SuspendServerJob;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Stripe\Event;

/**
 * Handles `charge.dispute.created` events. A chargeback (dispute) means
 * the customer is contesting the charge with their bank — Peregrine must
 * suspend the server immediately to stop accruing further hosting cost
 * BUT must NOT schedule deletion : the dispute may be resolved in
 * Peregrine's favour, and admin needs the server intact to either
 * unsuspend or delete based on the outcome.
 *
 * Server lookup uses `payment_intent_id` (Stripe disputes carry the
 * parent payment_intent in `data.object.payment_intent`).
 */
final class HandleDisputeAction
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(Event $event): array
    {
        $dispute = $event->data->object;
        $disputeData = is_object($dispute) ? $dispute->toArray() : (array) $dispute;

        $paymentIntentId = is_string($disputeData['payment_intent'] ?? null)
            ? $disputeData['payment_intent']
            : null;

        if ($paymentIntentId === null) {
            return ['skipped' => 'no_payment_intent_on_dispute'];
        }

        $server = Server::where('payment_intent_id', $paymentIntentId)->first();
        if ($server === null) {
            Log::warning('charge.dispute.created: no Server matches payment_intent_id', [
                'event_id' => $event->id,
                'payment_intent' => $paymentIntentId,
            ]);
            return ['skipped' => 'server_not_found', 'payment_intent' => $paymentIntentId];
        }

        // scheduleDeletion=false : disputes can resolve in our favour, the
        // server must stay intact for admin to decide post-resolution.
        SuspendServerJob::dispatch(
            eventId: $event->id,
            stripeSubscriptionId: (string) ($server->stripe_subscription_id ?? ''),
            scheduleDeletion: false,
        );

        return [
            'dispatched' => 'SuspendServerJob',
            'reason' => 'dispute',
            'server_id' => $server->id,
            'dispute_id' => $disputeData['id'] ?? null,
            'dispute_status' => $disputeData['status'] ?? null,
        ];
    }
}
