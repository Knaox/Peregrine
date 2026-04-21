<?php

namespace App\Listeners;

use App\Events\TwoFactorEnabled;
use App\Notifications\TwoFactorEnabledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTwoFactorEnabledNotification implements ShouldQueue
{
    public function handle(TwoFactorEnabled $event): void
    {
        $event->user->notify(new TwoFactorEnabledNotification($event->ip, $event->userAgent));
    }
}
