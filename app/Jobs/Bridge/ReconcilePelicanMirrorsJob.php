<?php

namespace App\Jobs\Bridge;

use App\Models\BridgeSyncLog;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Sync\InfrastructureSync;
use App\Services\Sync\ServerSync;
use App\Services\Sync\UserSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles local Pelican mirror tables against the live Pelican API.
 *
 * Two scopes :
 *
 *   safetyNet (hourly when webhook is enabled)
 *     Only ressources hit by Pelican mass-update bypasses :
 *       - Backup (PruneOrphanedBackupsCommand silently marks failed)
 *       - Allocation (TransferServerService / ServerDeletionService /
 *         BuildModificationService bulk reassign)
 *     Webhook handles everything else.
 *
 *   fullSync (daily when webhook is disabled — fallback polling mode)
 *     All mirrored ressources (Server, User, Node, Egg, Backup, Database,
 *     DatabaseHost, Allocation, ServerTransfer). Slower but covers admins
 *     who didn't configure the webhook.
 *
 * Schedule choice is automatic in routes/console.php — reads
 * `pelican_webhook_enabled` setting and dispatches with the right scope.
 */
class ReconcilePelicanMirrorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const SCOPE_SAFETY_NET = 'safetyNet';
    public const SCOPE_FULL_SYNC = 'fullSync';

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        public readonly string $scope = self::SCOPE_SAFETY_NET,
    ) {}

    public function handle(
        PelicanApplicationService $pelican,
        UserSync $userSync,
        ServerSync $serverSync,
        InfrastructureSync $infraSync,
    ): void {
        $started = microtime(true);
        $stats = ['scope' => $this->scope];

        // Per-server resources (backups / databases / allocations / subusers)
        // are not mirrored anymore — the SPA reads them live. Reconciliation
        // only keeps the four CORE infrastructure tables (nodes, eggs, users,
        // servers) in sync, and only when the operator chose full-sync.
        if ($this->scope === self::SCOPE_FULL_SYNC) {
            $stats = array_merge($stats, $this->reconcileFullSync($infraSync));
        }

        $stats['duration_ms'] = (int) ((microtime(true) - $started) * 1000);

        BridgeSyncLog::create([
            'action' => 'pelican_mirror_reconcile',
            'shop_plan_id' => null,
            'request_payload' => $stats,
            'response_status' => 200,
            'response_body' => null,
            'ip_address' => '127.0.0.1',
            'signature_valid' => true,
            'attempted_at' => now(),
        ]);

        Log::info('ReconcilePelicanMirrorsJob: completed', $stats);
    }

    /**
     * @return array<string, int>
     */
    private function reconcileFullSync(InfrastructureSync $infraSync): array
    {
        $eggsSynced = $infraSync->syncEggs();
        $nodesSynced = $infraSync->syncNodes();

        return [
            'eggs_synced' => $eggsSynced,
            'nodes_synced' => $nodesSynced,
        ];
    }
}
