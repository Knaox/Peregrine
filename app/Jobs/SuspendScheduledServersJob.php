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
 * Hourly sweep — phase 1 of the two-phase cancellation lifecycle.
 *
 * When a customer disables auto-renew, SubscriptionUpdateJob records on the
 * (still active) server :
 *   scheduled_suspension_at = paid period end
 *   scheduled_deletion_at   = period end + grace period
 *
 * This job suspends each server whose suspension date has passed (Pelican +
 * status='suspended'), clears `scheduled_suspension_at` (done) and KEEPS
 * `scheduled_deletion_at` so `PurgeScheduledServerDeletionsJob` hard-deletes
 * it after the grace period.
 *
 * Idempotent : only ACTIVE servers are picked up, so a server already
 * suspended by a parallel `customer.subscription.deleted` is skipped. A
 * per-server Pelican failure is logged and the row is left untouched so the
 * next hourly run retries it (the batch is not aborted).
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
            ->whereNotNull('scheduled_suspension_at')
            ->where('scheduled_suspension_at', '<=', now())
            ->where('status', 'active')
            ->get();

        foreach ($due as $server) {
            try {
                if ($server->pelican_server_id !== null) {
                    $pelican->suspendServer($server->pelican_server_id);
                }
            } catch (\Throwable $e) {
                // Keep scheduled_suspension_at so the next hourly run retries.
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
