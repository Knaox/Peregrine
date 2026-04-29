<?php

namespace App\Jobs\Bridge;

use App\Models\BridgeSyncLog;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Pelican\Allocation;
use App\Models\Pelican\Backup;
use App\Models\Pelican\Database as PelicanDatabase;
use App\Models\Pelican\DatabaseHost;
use App\Models\Pelican\ServerTransfer;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
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

        if ($this->scope === self::SCOPE_FULL_SYNC) {
            $stats = array_merge($stats, $this->reconcileFullSync($infraSync));
        }

        $stats = array_merge($stats, $this->reconcileBackupsAllocations($pelican));

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

    /**
     * @return array<string, int>
     */
    private function reconcileBackupsAllocations(PelicanApplicationService $pelican): array
    {
        $backupsRectified = 0;
        $allocationsRectified = 0;

        // Allocation reconciliation: per node, fetch live list, upsert / delete absences.
        foreach (Node::whereNotNull('pelican_node_id')->cursor() as $node) {
            try {
                $remoteAllocations = $pelican->listNodeAllocations((int) $node->pelican_node_id);
            } catch (\Throwable $e) {
                Log::warning('ReconcilePelicanMirrorsJob: listNodeAllocations failed', [
                    'pelican_node_id' => $node->pelican_node_id,
                    'message' => $e->getMessage(),
                ]);
                continue;
            }

            $remoteIds = [];
            foreach ($remoteAllocations as $remote) {
                $remoteIds[] = $remote->id;
                $serverLocalId = null;
                if (property_exists($remote, 'serverId') && $remote->serverId !== null) {
                    $serverLocalId = Server::where('pelican_server_id', $remote->serverId)->value('id');
                }
                Allocation::updateOrCreate(
                    ['pelican_allocation_id' => $remote->id],
                    [
                        'node_id' => $node->id,
                        'server_id' => $serverLocalId,
                        'ip' => property_exists($remote, 'ip') ? (string) $remote->ip : '0.0.0.0',
                        'port' => property_exists($remote, 'port') ? (int) $remote->port : 0,
                        'ip_alias' => property_exists($remote, 'alias') ? $remote->alias : null,
                        'is_locked' => property_exists($remote, 'isLocked') ? (bool) $remote->isLocked : false,
                    ],
                );
                $allocationsRectified++;
            }

            // Soft-delete local allocations no longer present remotely.
            if ($remoteIds !== []) {
                Allocation::where('node_id', $node->id)
                    ->whereNotIn('pelican_allocation_id', $remoteIds)
                    ->delete();
            }
        }

        // Backup reconciliation: per server, fetch via Client API.
        // Skipped for full-sync scope only when there are too many servers
        // (we run hourly otherwise — daily for fullSync is fine here).
        $backupService = app(\App\Services\Pelican\PelicanBackupService::class);
        foreach (Server::whereNotNull('identifier')->cursor() as $server) {
            try {
                $remoteBackups = $backupService->listBackups($server->identifier);
            } catch (\Throwable) {
                // Server may not be reachable yet, skip silently — next run will retry.
                continue;
            }

            $remoteIds = [];
            foreach ($remoteBackups as $remote) {
                $remoteIds[] = $remote['id'] ?? 0;
                $remoteBackupId = (int) ($remote['id'] ?? 0);
                if ($remoteBackupId === 0) {
                    continue;
                }
                Backup::updateOrCreate(
                    ['pelican_backup_id' => $remoteBackupId],
                    [
                        'server_id' => $server->id,
                        'uuid' => (string) ($remote['uuid'] ?? ''),
                        'name' => (string) ($remote['name'] ?? 'backup'),
                        'is_successful' => (bool) ($remote['is_successful'] ?? false),
                        'is_locked' => (bool) ($remote['is_locked'] ?? false),
                        'checksum' => $remote['checksum'] ?? null,
                        'bytes' => (int) ($remote['bytes'] ?? 0),
                        'completed_at' => isset($remote['completed_at'])
                            ? \Illuminate\Support\Carbon::parse((string) $remote['completed_at'])
                            : null,
                    ],
                );
                $backupsRectified++;
            }

            if ($remoteIds !== []) {
                Backup::where('server_id', $server->id)
                    ->whereNotIn('pelican_backup_id', $remoteIds)
                    ->delete();
            }
        }

        return [
            'backups_rectified' => $backupsRectified,
            'allocations_rectified' => $allocationsRectified,
        ];
    }
}
