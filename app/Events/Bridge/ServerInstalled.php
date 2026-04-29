<?php

namespace App\Events\Bridge;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by SyncServerFromPelicanWebhookJob once Pelican's webhook signals
 * the server has finished installing (status flips from `installing` to
 * null/running and the local row's status transitions provisioning→active).
 *
 * Distinct from `ServerProvisioned` which fires right after ProvisionServerJob's
 * Pelican createServer call returns — at that moment the install hasn't
 * started yet, and the local server still sits in `provisioning` until
 * the webhook arrives.
 */
class ServerInstalled
{
    use Dispatchable;

    public function __construct(
        public readonly Server $server,
        public readonly User $user,
    ) {}
}
