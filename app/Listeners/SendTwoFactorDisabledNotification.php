<?php

namespace App\Listeners;

use App\Events\TwoFactorDisabled;
use App\Notifications\TwoFactorDisabledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTwoFactorDisabledNotification implements ShouldQueue
{
    public function handle(TwoFactorDisabled $event): void
    {
        $event->user->notify(new TwoFactorDisabledNotification($event->ip, $event->userAgent));
    }
}
