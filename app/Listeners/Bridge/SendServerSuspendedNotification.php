<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\ServerSuspended;
use App\Notifications\Bridge\ServerSuspendedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendServerSuspendedNotification implements ShouldQueue
{
    public function handle(ServerSuspended $event): void
    {
        $event->user->notify(new ServerSuspendedNotification($event->server));
    }
}
