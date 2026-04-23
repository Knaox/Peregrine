<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Daily cron job that hard-deletes servers whose grace period has expired.
 *
 * Picks rows where `scheduled_deletion_at IS NOT NULL`, the timestamp is in
 * the past, AND status is `suspended` (extra safety so we never delete an
 * `active` server even if a UI bug set the column).
 *
 * For each match : delete the Pelican server, then delete the local row
 * (which cascades to server_user pivot, schedules, etc. via DB constraints).
 *
 * Idempotent : if Pelican delete fails for one server, the loop logs the
 * error and continues with the next. The failed row stays in DB and is
 * retried at the next cron run.
 *
 * Scheduled in `routes/console.php` :
 *   `Schedule::job(new PurgeScheduledServerDeletionsJob)->daily()->at('03:00')`
 */
class PurgeScheduledServerDeletionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Cron retries us tomorrow if today fails completely.

    public int $timeout = 600; // 10 min — generous for batches.

    public function handle(PelicanApplicationService $pelican): void
    {
        $candidates = Server::query()
            ->whereNotNull('scheduled_deletion_at')
            ->where('scheduled_deletion_at', '<=', now())
            ->where('status', 'suspended')
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $deleted = 0;
        $failed = 0;

        foreach ($candidates as $server) {
            try {
                if ($server->pelican_server_id !== null) {
                    $pelican->deleteServer($server->pelican_server_id);
                }
                $server->delete();
                $deleted++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('PurgeScheduledServerDeletionsJob: failed to delete server, will retry tomorrow', [
                    'server_id' => $server->id,
                    'pelican_server_id' => $server->pelican_server_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Log::info('PurgeScheduledServerDeletionsJob: cycle complete', [
            'deleted' => $deleted,
            'failed' => $failed,
            'candidates' => $candidates->count(),
        ]);
    }
}
