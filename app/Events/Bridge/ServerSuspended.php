<?php

namespace App\Events\Bridge;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by SuspendServerJob after a successful Pelican suspendServer call
 * AND a successful local update of `Server.status` (and optionally
 * `scheduled_deletion_at`). Listened to by SendServerSuspendedNotification
 * which emails the customer with the recovery window.
 */
class ServerSuspended
{
    use Dispatchable;

    public function __construct(
        public readonly Server $server,
        public readonly User $user,
    ) {}
}
