<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\ServerSuspended;
use App\Notifications\Bridge\ServerSuspendedNotification;
use App\Services\Bridge\BridgeModeService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendServerSuspendedNotification implements ShouldQueue
{
    public function handle(ServerSuspended $event): void
    {
        // Same Shop+Stripe-only gate as SendServerReadyNotification.
        if (! app(BridgeModeService::class)->current()->isShopStripe()) {
            return;
        }
        $event->user->notify(new ServerSuspendedNotification($event->server));
    }
}
