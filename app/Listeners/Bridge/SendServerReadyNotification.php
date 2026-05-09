<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\ServerProvisioned;
use App\Notifications\Bridge\ServerReadyNotification;
use App\Services\Integrations\IntegrationStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendServerReadyNotification implements ShouldQueue
{
    public function handle(ServerProvisioned $event): void
    {
        // Disabled : the customer-facing "your server is ready" message
        // is now sent by `SendServerInstalledNotification` only — when
        // Pelican's install script actually finishes. Sending this
        // earlier "we created the row" version on top of the install-
        // complete one was confusing customers who saw two emails for
        // the same purchase. The listener stays registered (so any
        // existing auto-discovered binding doesn't error) but no longer
        // emits an email.
        //
        // To restore the dual-email behaviour, drop this no-op and
        // reinstate the original
        // `$event->user->notify(new ServerReadyNotification(...))`.
    }
}
