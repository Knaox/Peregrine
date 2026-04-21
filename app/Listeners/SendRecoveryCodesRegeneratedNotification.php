<?php

namespace App\Listeners;

use App\Events\RecoveryCodesRegenerated;
use App\Notifications\RecoveryCodesRegeneratedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRecoveryCodesRegeneratedNotification implements ShouldQueue
{
    public function handle(RecoveryCodesRegenerated $event): void
    {
        $event->user->notify(new RecoveryCodesRegeneratedNotification($event->ip, $event->userAgent));
    }
}
