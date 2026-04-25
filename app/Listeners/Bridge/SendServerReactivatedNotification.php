<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\ServerReactivated;
use App\Notifications\Bridge\ServerReactivatedNotification;
use App\Services\Bridge\BridgeModeService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendServerReactivatedNotification implements ShouldQueue
{
    public function handle(ServerReactivated $event): void
    {
        if (! app(BridgeModeService::class)->current()->isShopStripe()) {
            return;
        }
        $event->user->notify(new ServerReactivatedNotification($event->server));
    }
}
