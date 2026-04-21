<?php

namespace App\Listeners;

use App\Events\OAuthProviderLinked;
use App\Notifications\OAuthProviderLinkedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOAuthProviderLinkedNotification implements ShouldQueue
{
    public function handle(OAuthProviderLinked $event): void
    {
        $event->user->notify(new OAuthProviderLinkedNotification(
            $event->provider,
            $event->ip,
            $event->userAgent,
        ));
    }
}
