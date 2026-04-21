<?php

namespace App\Listeners;

use App\Events\OAuthProviderUnlinked;
use App\Notifications\OAuthProviderUnlinkedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOAuthProviderUnlinkedNotification implements ShouldQueue
{
    public function handle(OAuthProviderUnlinked $event): void
    {
        $event->user->notify(new OAuthProviderUnlinkedNotification(
            $event->provider,
            $event->ip,
            $event->userAgent,
        ));
    }
}
