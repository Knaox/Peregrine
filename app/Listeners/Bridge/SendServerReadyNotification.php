<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\ServerProvisioned;
use App\Notifications\Bridge\ServerReadyNotification;
use App\Services\Bridge\BridgeModeService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendServerReadyNotification implements ShouldQueue
{
    public function handle(ServerProvisioned $event): void
    {
        // Bridge emails are commercial messages tied to the Shop+Stripe
        // lifecycle. In Paymenter mode Paymenter sends its own emails and we
        // must not double-up. In Disabled mode we have no business reason
        // to fire from this code path.
        if (! app(BridgeModeService::class)->current()->isShopStripe()) {
            return;
        }
        $event->user->notify(new ServerReadyNotification($event->server));
    }
}
