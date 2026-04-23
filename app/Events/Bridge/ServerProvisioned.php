<?php

namespace App\Events\Bridge;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by ProvisionServerJob after a successful Pelican server creation
 * AND a successful local row update with the pelican_server_id. Listened
 * to by SendServerReadyNotification (queued) which sends the customer's
 * "Your server is ready" email.
 *
 * Lives outside the dispatching job so any other listener (analytics,
 * Slack notification, plugin hook…) can subscribe without coupling to
 * the provisioning code.
 */
class ServerProvisioned
{
    use Dispatchable;

    public function __construct(
        public readonly Server $server,
        public readonly User $user,
    ) {}
}
