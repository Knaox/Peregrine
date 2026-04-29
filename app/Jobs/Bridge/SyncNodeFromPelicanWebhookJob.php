<?php

namespace App\Jobs\Bridge;

use App\Enums\PelicanEventKind;
use App\Models\Node;
use App\Models\ServerPlan;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors a Pelican Node change into the local DB.
 *
 * Triggered by Pelican webhooks: created / updated / deleted on
 * `App\Models\Node`. Replaces the manual `sync:nodes` command for the
 * common case of an admin adding / editing / removing a node in Pelican.
 *
 * Behaviour:
 *
 *   NodeCreated / NodeUpdated
 *     - Refetch via Pelican Application API (the webhook payload alone
 *       lacks the location_id and a few derived fields), upsert by
 *       pelican_node_id.
 *     - 404 → nothing to mirror, log + return (no retry).
 *
 *   NodeDeleted
 *     - Lookup local by pelican_node_id.
 *     - If any local server still references this node → log warning and
 *       SKIP the delete (would leave servers orphaned with FK-like drift).
 *       Admin must reassign / delete those servers first.
 *     - Otherwise hard-delete the local node row.
 */
class SyncNodeFromPelicanWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 30;

    public function __construct(
        public readonly int $pelicanNodeId,
        public readonly PelicanEventKind $eventKind,
    ) {}

    public function handle(PelicanApplicationService $pelican): void
    {
        if ($this->eventKind === PelicanEventKind::NodeDeleted) {
            $this->handleDeletion();
            return;
        }

        try {
            $pelicanNode = $pelican->getNode($this->pelicanNodeId);
        } catch (RequestException $e) {
            if ($e->response?->status() === 404) {
                Log::info('SyncNodeFromPelicanWebhookJob: pelican node not found, skipping', [
                    'pelican_node_id' => $this->pelicanNodeId,
                    'event_kind' => $this->eventKind->value,
                ]);
                return;
            }
            throw $e;
        }

        $node = Node::updateOrCreate(
            ['pelican_node_id' => $this->pelicanNodeId],
            [
                'name' => $pelicanNode->name,
                'fqdn' => $pelicanNode->fqdn,
                'memory' => $pelicanNode->memory,
                'disk' => $pelicanNode->disk,
                'location' => $pelicanNode->location,
            ],
        );

        Log::info('SyncNodeFromPelicanWebhookJob: node mirrored', [
            'pelican_node_id' => $this->pelicanNodeId,
            'local_node_id' => $node->id,
            'event_kind' => $this->eventKind->value,
            'was_recently_created' => $node->wasRecentlyCreated,
        ]);
    }

    private function handleDeletion(): void
    {
        $node = Node::where('pelican_node_id', $this->pelicanNodeId)->first();
        if ($node === null) {
            return;
        }

        // Servers don't FK to Node locally (Peregrine doesn't mirror node
        // assignment per server). The only local references are ServerPlan
        // rows (default_node_id + allowed_node_ids). Refuse to delete a node
        // still referenced by any plan to avoid breaking provisioning.
        $referencingPlans = ServerPlan::query()
            ->where('default_node_id', $node->id)
            ->orWhereJsonContains('allowed_node_ids', $node->id)
            ->count();

        if ($referencingPlans > 0) {
            Log::warning('SyncNodeFromPelicanWebhookJob: refusing to delete node referenced by server plans', [
                'pelican_node_id' => $this->pelicanNodeId,
                'local_node_id' => $node->id,
                'referencing_plans' => $referencingPlans,
            ]);
            return;
        }

        $node->delete();

        Log::info('SyncNodeFromPelicanWebhookJob: node removed', [
            'pelican_node_id' => $this->pelicanNodeId,
            'local_node_id' => $node->id,
        ]);
    }
}
