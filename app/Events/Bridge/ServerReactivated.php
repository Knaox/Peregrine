<?php

namespace App\Events\Bridge;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by StripeEventHandlers::handleResubscribe after a customer has
 * gone through a fresh Stripe checkout to revive a previously-suspended
 * server (instead of provisioning a new one). The local row is reused,
 * Pelican is unsuspended, and scheduled_deletion_at is cleared.
 *
 * Distinct from `ServerProvisioned` (= brand new server) and from
 * `ServerInstalled` (= Pelican install script finished). Triggers the
 * "Your server is back online" mail.
 */
class ServerReactivated
{
    use Dispatchable;

    public function __construct(
        public readonly Server $server,
        public readonly User $user,
    ) {}
}
