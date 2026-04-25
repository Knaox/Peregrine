<?php

namespace App\Events\Bridge;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by MonitorServerInstallationJob once Pelican reports the server's
 * status has flipped out of `installing` (i.e. install script finished and
 * Wings has the server in its idle/running state).
 *
 * Distinct from `ServerProvisioned` which fires right after the Pelican
 * createServer call returns — at that moment the install hasn't started yet.
 */
class ServerInstalled
{
    use Dispatchable;

    public function __construct(
        public readonly Server $server,
        public readonly User $user,
    ) {}
}
