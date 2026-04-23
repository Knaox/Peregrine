<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\ServerProvisioned;
use App\Notifications\Bridge\ServerReadyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendServerReadyNotification implements ShouldQueue
{
    public function handle(ServerProvisioned $event): void
    {
        $event->user->notify(new ServerReadyNotification($event->server));
    }
}
