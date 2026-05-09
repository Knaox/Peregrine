<?php

declare(strict_types=1);

namespace App\Bridge\Stripe\Actions;

use App\Jobs\SuspendServerJob;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Stripe\Event;

/**
 * Handles `charge.refunded` events. A refund means the customer got
 * their money back — the server should be suspended and scheduled for
 * deletion at the regular grace period (admin retains the option to
 * cancel the deletion via Filament if it's a partial refund or a
 * billing-only correction).
 *
 * Server lookup is done via `payment_intent_id` (already on the Server
 * row from the original checkout). Stripe's `data.object` for refund
 * events embeds the parent `payment_intent`.
 */
final class HandleRefundAction
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(Event $event): array
    {
        $charge = $event->data->object;
        $chargeData = is_object($charge) ? $charge->toArray() : (array) $charge;

        $paymentIntentId = is_string($chargeData['payment_intent'] ?? null)
            ? $chargeData['payment_intent']
            : null;

        if ($paymentIntentId === null) {
            return ['skipped' => 'no_payment_intent_on_charge'];
        }

        $server = Server::where('payment_intent_id', $paymentIntentId)->first();
        if ($server === null) {
            Log::warning('charge.refunded: no Server matches payment_intent_id', [
                'event_id' => $event->id,
                'payment_intent' => $paymentIntentId,
            ]);
            return ['skipped' => 'server_not_found', 'payment_intent' => $paymentIntentId];
        }

        SuspendServerJob::dispatch(
            eventId: $event->id,
            stripeSubscriptionId: (string) ($server->stripe_subscription_id ?? ''),
            scheduleDeletion: true,
        );

        return [
            'dispatched' => 'SuspendServerJob',
            'reason' => 'refund',
            'server_id' => $server->id,
            'amount_refunded' => $chargeData['amount_refunded'] ?? null,
        ];
    }
}
