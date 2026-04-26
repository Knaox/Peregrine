<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\PaymentConfirmed;
use App\Notifications\Bridge\PaymentConfirmedNotification;
use App\Services\Bridge\BridgeModeService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentConfirmedNotification implements ShouldQueue
{
    public function handle(PaymentConfirmed $event): void
    {
        // Trial checkouts fire checkout.session.completed with amount_total=0.
        // Sending a "thanks for your payment" receipt for €0 would mislead the
        // customer — skip the notification in that case. Stripe will fire a real
        // invoice.payment_succeeded once the trial converts to a paid charge.
        if ($event->amountCents === 0) {
            return;
        }
        // Same Shop+Stripe-only gate as the rest of the Bridge listeners.
        if (! app(BridgeModeService::class)->current()->isShopStripe()) {
            return;
        }
        $event->user->notify(new PaymentConfirmedNotification(
            plan: $event->plan,
            amountCents: $event->amountCents,
            currency: $event->currency,
            invoiceId: $event->invoiceId,
        ));
    }
}
