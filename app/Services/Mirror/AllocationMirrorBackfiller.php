<?php

namespace App\Services\Mirror;

use App\Models\Node;
use App\Models\Pelican\Allocation;
use App\Models\Server;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Support\Facades\Log;

/**
 * Backfills `pelican_allocations` from Pelican, keeping ONLY allocations
 * attributed to a server.
 *
 * Why this exists separately from PelicanMirrorSyncer::syncAllocations() :
 * the legacy syncer relied on a DTO field that was never populated (the
 * `?include=server` parameter wasn't passed) and ended up writing every
 * unassigned port row into the mirror table. This backfiller :
 *
 *   1. Calls listNodeAllocations() with `?include=server` so the response
 *      carries the owning server's id.
 *   2. Skips any row where `serverId` is null (port libre côté Pelican —
 *      pas la peine d'occuper la table miroir avec).
 *   3. Removes mirror rows whose Pelican id is no longer assigned (the
 *      port has been freed since the previous backfill / webhook miss).
 *
 * Idempotent. Safe to re-run any time. Returns a structured report so the
 * orchestrator can surface counts in the Filament page.
 */
final class AllocationMirrorBackfiller
{
    public function __construct(
        private readonly PelicanApplicationService $pelican,
    ) {}

    /**
     * @return array{processed:int,written:int,skipped_unassigned:int,removed_orphans:int,errors:int}
     */
    public function run(): array
    {
        $report = [
            'processed' => 0,
            'written' => 0,
            'skipped_unassigned' => 0,
            'removed_orphans' => 0,
            'errors' => 0,
        ];

        $seenIds = [];

        foreach (Node::query()->whereNotNull('pelican_node_id')->cursor() as $node) {
            try {
                $remote = $this->pelican->listNodeAllocations(
                    (int) $node->pelican_node_id,
                    includeServer: true,
                );
            } catch (\Throwable $e) {
                $report['errors']++;
                Log::warning('AllocationMirrorBackfiller: node fetch failed', [
                    'node_id' => $node->id,
                    'pelican_node_id' => $node->pelican_node_id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($remote as $allocation) {
                $report['processed']++;

                if ($allocation->serverId === null) {
                    $report['skipped_unassigned']++;
                    continue;
                }

                $localServerId = Server::query()
                    ->where('pelican_server_id', $allocation->serverId)
                    ->value('id');

                if ($localServerId === null) {
                    // Pelican knows the server, we don't yet — skip rather
                    // than write a NULL FK. Will be picked up next run after
                    // the server mirror catches up.
                    $report['skipped_unassigned']++;
                    continue;
                }

                Allocation::query()->updateOrCreate(
                    ['pelican_allocation_id' => $allocation->id],
                    [
                        'node_id' => $node->id,
                        'server_id' => $localServerId,
                        'ip' => $allocation->ip,
                        'ip_alias' => $allocation->ipAlias,
                        'port' => $allocation->port,
                        'notes' => $allocation->notes,
                        'is_locked' => false,
                    ],
                );

                $seenIds[] = $allocation->id;
                $report['written']++;
            }
        }

        $report['removed_orphans'] = $this->pruneOrphans($seenIds);

        return $report;
    }

    /**
     * Soft-delete mirror rows whose Pelican id wasn't seen during this run.
     * Either the allocation was freed on Pelican (server detached) or
     * deleted entirely. Either way, it should disappear from the mirror.
     *
     * @param  list<int>  $seenIds
     */
    private function pruneOrphans(array $seenIds): int
    {
        if ($seenIds === []) {
            return 0;
        }

        return Allocation::query()
            ->whereNotIn('pelican_allocation_id', $seenIds)
            ->delete();
    }
}
