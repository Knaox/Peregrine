<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\ServerInstalled;
use App\Notifications\Bridge\ServerInstalledNotification;
use App\Services\Bridge\BridgeModeService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendServerInstalledNotification implements ShouldQueue
{
    public function handle(ServerInstalled $event): void
    {
        if (! app(BridgeModeService::class)->current()->isShopStripe()) {
            return;
        }
        $event->user->notify(new ServerInstalledNotification($event->server));
    }
}
