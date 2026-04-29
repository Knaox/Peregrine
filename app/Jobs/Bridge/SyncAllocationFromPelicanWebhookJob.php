<?php

namespace App\Jobs\Bridge;

use App\Enums\PelicanEventKind;
use App\Models\Node;
use App\Models\Pelican\Allocation;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors a Pelican Allocation change locally.
 *
 * server_id is nullable (free allocations). node_id is normally set.
 * Mass-update bypasses (TransferServerService, ServerDeletionService,
 * BuildModificationService) miss this hook — the hourly safety-net
 * reconciliation rebuilds via listNodeAllocations.
 */
class SyncAllocationFromPelicanWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $pelicanAllocationId,
        public readonly array $payload,
        public readonly PelicanEventKind $eventKind,
    ) {}

    public function handle(): void
    {
        if ($this->eventKind === PelicanEventKind::AllocationDeleted) {
            Allocation::where('pelican_allocation_id', $this->pelicanAllocationId)->delete();
            return;
        }

        // Skip free allocations — same rule as the backfill / sync job.
        // The mirror only tracks allocations attributed to a server. When
        // the allocation transitions free → assigned (server attaches it),
        // an `updated: Allocation` webhook fires with a non-null server_id
        // and we mirror it then. Conversely if a server detaches it,
        // we drop the mirror row to mirror the free state.
        $rawServerId = $this->payload['server_id'] ?? null;
        if ($rawServerId === null || $rawServerId === '') {
            Allocation::where('pelican_allocation_id', $this->pelicanAllocationId)->delete();

            Log::info('SyncAllocationFromPelicanWebhookJob: free allocation skipped', [
                'pelican_allocation_id' => $this->pelicanAllocationId,
            ]);

            return;
        }

        $serverId = Server::where('pelican_server_id', (int) $rawServerId)->value('id');
        if ($serverId === null) {
            // Server known to Pelican but not yet to us — bail and let the
            // server-mirror catch up first (a Server webhook will arrive).
            Log::warning('SyncAllocationFromPelicanWebhookJob: local server not found, skipping', [
                'pelican_allocation_id' => $this->pelicanAllocationId,
                'pelican_server_id' => (int) $rawServerId,
            ]);

            return;
        }

        $nodeId = null;
        if (isset($this->payload['node_id'])) {
            $nodeId = Node::where('pelican_node_id', (int) $this->payload['node_id'])->value('id');
        }

        Allocation::updateOrCreate(
            ['pelican_allocation_id' => $this->pelicanAllocationId],
            [
                'node_id' => $nodeId,
                'server_id' => $serverId,
                'ip' => (string) ($this->payload['ip'] ?? '0.0.0.0'),
                'port' => (int) ($this->payload['port'] ?? 0),
                'ip_alias' => $this->payload['ip_alias'] ?? null,
                'notes' => $this->payload['notes'] ?? null,
                'is_locked' => (bool) ($this->payload['is_locked'] ?? false),
                'pelican_created_at' => $this->parseDate($this->payload['created_at'] ?? null),
                'pelican_updated_at' => $this->parseDate($this->payload['updated_at'] ?? null),
            ],
        );

        Log::info('SyncAllocationFromPelicanWebhookJob: allocation mirrored', [
            'pelican_allocation_id' => $this->pelicanAllocationId,
            'server_id' => $serverId,
            'node_id' => $nodeId,
        ]);
    }

    private function parseDate(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
