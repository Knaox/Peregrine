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
