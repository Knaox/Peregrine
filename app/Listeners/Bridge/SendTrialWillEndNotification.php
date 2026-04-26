<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\TrialWillEnd;
use App\Notifications\Bridge\TrialWillEndNotification;
use App\Services\Bridge\BridgeModeService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTrialWillEndNotification implements ShouldQueue
{
    public function handle(TrialWillEnd $event): void
    {
        // Same Shop+Stripe-only gate as the rest of the Bridge listeners —
        // Paymenter handles its own customer communications.
        if (! app(BridgeModeService::class)->current()->isShopStripe()) {
            return;
        }
        $event->user->notify(new TrialWillEndNotification(
            server: $event->server,
            trialEndsAt: $event->trialEndsAt,
        ));
    }
}
