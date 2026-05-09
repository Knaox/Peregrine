<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\ServerReactivated;
use App\Notifications\Bridge\ServerReactivatedNotification;
use App\Services\Integrations\IntegrationStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendServerReactivatedNotification implements ShouldQueue
{
    public function handle(ServerReactivated $event): void
    {
        if (! app(IntegrationStatusService::class)->hasStripeConfigured()) {
            return;
        }
        $event->user->notify(new ServerReactivatedNotification($event->server));
    }
}
