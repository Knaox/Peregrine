<?php

namespace App\Services\Sync;

use App\Models\Egg;
use App\Models\Nest;
use App\Models\Node;
use App\Services\DTOs\SyncComparison;
use App\Services\Pelican\PelicanApplicationService;

/**
 * Sync immutable infrastructure from Pelican → local DB: eggs (with their
 * nests) and nodes. Also exposes the cross-domain health check.
 */
class InfrastructureSync
{
    public function __construct(
        private PelicanApplicationService $pelicanService,
    ) {}

    /**
     * Synchronise all eggs from Pelican into the local database.
     * Pelican no longer has nests — eggs are listed directly.
     * If the egg has a nestId, we upsert the nest too.
     *
     * @return int Total number of eggs upserted.
     */
    public function syncEggs(): int
    {
        $eggs = $this->pelicanService->listEggs();
        $eggCount = 0;

        foreach ($eggs as $pelicanEgg) {
            $nestId = null;
            if ($pelicanEgg->nestId > 0) {
                $nest = Nest::updateOrCreate(
                    ['pelican_nest_id' => $pelicanEgg->nestId],
                    ['name' => 'Nest #' . $pelicanEgg->nestId],
                );
                $nestId = $nest->id;
            }

            Egg::updateOrCreate(
                ['pelican_egg_id' => $pelicanEgg->id],
                [
                    'nest_id' => $nestId,
                    'name' => $pelicanEgg->name,
                    'docker_image' => $pelicanEgg->dockerImage,
                    'startup' => $pelicanEgg->startup,
                    'description' => $pelicanEgg->description,
                    'tags' => $pelicanEgg->tags,
                    'features' => $pelicanEgg->features,
                ],
            );
            $eggCount++;
        }

        return $eggCount;
    }

    /**
     * Synchronise all nodes from Pelican into the local database.
     *
     * @return int Number of nodes upserted.
     */
    public function syncNodes(): int
    {
        $pelicanNodes = $this->pelicanService->listNodes();
        $count = 0;

        foreach ($pelicanNodes as $pelicanNode) {
            Node::updateOrCreate(
                ['pelican_node_id' => $pelicanNode->id],
                [
                    'name' => $pelicanNode->name,
                    'fqdn' => $pelicanNode->fqdn,
                    'memory' => $pelicanNode->memory,
                    'disk' => $pelicanNode->disk,
                    'location' => $pelicanNode->location,
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Verify consistency of all mappings between Pelican and the local database.
     *
     * @return array{users: SyncComparison, servers: SyncComparison, nodes_synced: int, eggs_synced: int}
     */
    public function healthCheck(UserSync $userSync, ServerSync $serverSync): array
    {
        $userComparison = $userSync->compareUsers();
        $serverComparison = $serverSync->compareServers();

        $pelicanNodes = $this->pelicanService->listNodes();
        $localNodeIds = Node::whereNotNull('pelican_node_id')
            ->pluck('pelican_node_id')
            ->toArray();
        $nodesSynced = count(array_filter(
            $pelicanNodes,
            fn ($node) => in_array($node->id, $localNodeIds, true),
        ));

        $localEggIds = Egg::whereNotNull('pelican_egg_id')
            ->pluck('pelican_egg_id')
            ->toArray();
        $eggsSynced = count($localEggIds);

        return [
            'users' => $userComparison,
            'servers' => $serverComparison,
            'nodes_synced' => $nodesSynced,
            'eggs_synced' => $eggsSynced,
        ];
    }
}
