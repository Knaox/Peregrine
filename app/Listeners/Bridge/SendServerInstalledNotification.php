<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\ServerInstalled;
use App\Notifications\Bridge\ServerInstalledNotification;
use App\Services\Integrations\IntegrationStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendServerInstalledNotification implements ShouldQueue
{
    public function handle(ServerInstalled $event): void
    {
        if (! app(IntegrationStatusService::class)->hasStripeConfigured()) {
            return;
        }
        $event->user->notify(new ServerInstalledNotification($event->server));
    }
}
