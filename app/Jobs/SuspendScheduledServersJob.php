<?php

namespace App\Jobs;

use App\Events\Bridge\ServerSuspended;
use App\Events\Mirror\BroadcastsServerMirror;
use App\Models\Server;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Periodic sweep — phase 1 of the two-phase cancellation lifecycle.
 *
 * When a customer disables auto-renew, SubscriptionUpdateJob records on the
 * (still active) server :
 *   scheduled_suspension_at = paid period end
 *   scheduled_deletion_at   = period end + grace period
 *
 * This job suspends each overdue server (Pelican + status='suspended'), clears
 * `scheduled_suspension_at` (done) and KEEPS `scheduled_deletion_at` so
 * `PurgeScheduledServerDeletionsJob` hard-deletes it after the grace period.
 *
 * Safety net (why it picks up more than just 'active' servers) :
 *  - It targets EVERY non-terminal server (active, running, stopped, offline,
 *    provisioning…), not only 'active', so a server that was powered OFF at its
 *    suspension date still gets suspended.
 *  - It also picks up servers whose DELETION date already passed but that are
 *    not suspended yet (a missed suspension), so the purge guard
 *    (status='suspended') never strands them — they get suspended here, then
 *    deleted on the next purge run.
 *  - `<= now()` means a date that elapsed while the host/scheduler was down is
 *    caught on the next sweep.
 *
 * Idempotent : already-suspended/terminated servers are excluded by the status
 * filter. A per-server Pelican failure is logged and the row is left untouched
 * so the next sweep retries it (the batch is not aborted).
 */
class SuspendScheduledServersJob implements ShouldQueue
{
    use BroadcastsServerMirror;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function handle(PelicanApplicationService $pelican): void
    {
        $due = Server::query()
            // Any non-terminal server (active, running, stopped, offline…),
            // so a powered-off server is still suspended at its date.
            ->whereNotIn('status', ['suspended', 'terminated'])
            ->where(function ($query): void {
                $query
                    ->where(function ($q): void {
                        $q->whereNotNull('scheduled_suspension_at')
                            ->where('scheduled_suspension_at', '<=', now());
                    })
                    // Self-heal: a server overdue for deletion but never
                    // suspended (missed suspension) gets suspended here so the
                    // purge's status='suspended' guard can then delete it.
                    ->orWhere(function ($q): void {
                        $q->whereNotNull('scheduled_deletion_at')
                            ->where('scheduled_deletion_at', '<=', now());
                    });
            })
            ->get();

        foreach ($due as $server) {
            try {
                if ($server->pelican_server_id !== null) {
                    $pelican->suspendServer($server->pelican_server_id);
                }
            } catch (\Throwable $e) {
                // Leave the row untouched so the next sweep retries it.
                Log::warning('SuspendScheduledServersJob: Pelican suspendServer failed, will retry next sweep', [
                    'server_id' => $server->id,
                    'message' => $e->getMessage(),
                ]);
                continue;
            }

            $server->update([
                'status' => 'suspended',
                'scheduled_suspension_at' => null,
            ]);
            $this->broadcastServerMirrorChanged($server);

            if ($server->user) {
                event(new ServerSuspended($server->fresh(), $server->user));
            }

            Log::info('SuspendScheduledServersJob: server suspended at scheduled date', [
                'server_id' => $server->id,
                'scheduled_deletion_at' => optional($server->scheduled_deletion_at)->toIso8601String(),
            ]);
        }
    }
}
