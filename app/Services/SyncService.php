<?php

namespace App\Services;

use App\Models\Egg;
use App\Models\Nest;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\DTOs\SyncComparison;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SyncService
{
    public function __construct(
        private PelicanApplicationService $pelicanService,
    ) {}

    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    /**
     * Compare Pelican users with local database users.
     *
     * - new:      Users that exist in Pelican but have no matching local record.
     * - synced:   Users that exist in both Pelican and the local database.
     * - orphaned: Local users whose pelican_user_id no longer exists on Pelican.
     */
    public function compareUsers(): SyncComparison
    {
        $pelicanUsers = $this->pelicanService->listUsers();
        $localUsers = User::whereNotNull('pelican_user_id')->get();

        $localPelicanIds = $localUsers->pluck('pelican_user_id')->toArray();
        $remotePelicanIds = array_map(fn ($u) => $u->id, $pelicanUsers);

        $new = [];
        $synced = [];
        $orphaned = [];

        foreach ($pelicanUsers as $pelicanUser) {
            if (in_array($pelicanUser->id, $localPelicanIds, true)) {
                $synced[] = $pelicanUser;
            } else {
                $new[] = $pelicanUser;
            }
        }

        foreach ($localUsers as $localUser) {
            if (! in_array($localUser->pelican_user_id, $remotePelicanIds, true)) {
                $orphaned[] = $localUser;
            }
        }

        return new SyncComparison(
            new: $new,
            synced: $synced,
            orphaned: $orphaned,
        );
    }

    /**
     * Import selected Pelican users into the local database.
     *
     * @param int[] $pelicanUserIds
     *
     * @return int Number of users imported.
     */
    public function importUsers(array $pelicanUserIds): int
    {
        $imported = 0;

        foreach ($pelicanUserIds as $pelicanUserId) {
            $pelicanUser = $this->pelicanService->getUser($pelicanUserId);

            $exists = User::where('pelican_user_id', $pelicanUser->id)->exists()
                || User::where('email', $pelicanUser->email)->exists();

            if ($exists) {
                continue;
            }

            User::create([
                'name' => $pelicanUser->firstName . ' ' . $pelicanUser->lastName,
                'email' => $pelicanUser->email,
                'password' => Hash::make(Str::random(32)),
                'pelican_user_id' => $pelicanUser->id,
            ]);

            $imported++;
        }

        return $imported;
    }

    // -------------------------------------------------------------------------
    // Servers
    // -------------------------------------------------------------------------

    /**
     * Compare Pelican servers with local database servers.
     */
    public function compareServers(): SyncComparison
    {
        $pelicanServers = $this->pelicanService->listServers();
        $localServers = Server::whereNotNull('pelican_server_id')->get();

        $localPelicanIds = $localServers->pluck('pelican_server_id')->toArray();
        $remotePelicanIds = array_map(fn ($s) => $s->id, $pelicanServers);

        $new = [];
        $synced = [];
        $orphaned = [];

        foreach ($pelicanServers as $pelicanServer) {
            if (in_array($pelicanServer->id, $localPelicanIds, true)) {
                $synced[] = $pelicanServer;
            } else {
                $new[] = $pelicanServer;
            }
        }

        foreach ($localServers as $localServer) {
            if (! in_array($localServer->pelican_server_id, $remotePelicanIds, true)) {
                $orphaned[] = $localServer;
            }
        }

        return new SyncComparison(
            new: $new,
            synced: $synced,
            orphaned: $orphaned,
        );
    }

    /**
     * Import selected servers into the local database.
     *
     * Each element of $serverImports should contain:
     *   - pelican_server_id: int
     *   - user_id: int (local user ID to associate with)
     *
     * @param array<int, array{pelican_server_id: int, user_id: int}> $serverImports
     *
     * @return int Number of servers imported.
     */
    public function importServers(array $serverImports): int
    {
        $imported = 0;

        foreach ($serverImports as $import) {
            $pelicanServerId = $import['pelican_server_id'];
            $userId = $import['user_id'];

            if (Server::where('pelican_server_id', $pelicanServerId)->exists()) {
                continue;
            }

            $pelicanServer = $this->pelicanService->getServer($pelicanServerId);

            Server::create([
                'pelican_server_id' => $pelicanServer->id,
                'user_id' => $userId,
                'name' => $pelicanServer->name,
                'status' => $pelicanServer->isSuspended ? 'suspended' : 'active',
                'egg_id' => Egg::where('pelican_egg_id', $pelicanServer->eggId)->value('id'),
            ]);

            $imported++;
        }

        return $imported;
    }

    // -------------------------------------------------------------------------
    // Eggs & Nests
    // -------------------------------------------------------------------------

    /**
     * Synchronise all nests and eggs from Pelican into the local database.
     *
     * @return int Total number of eggs upserted.
     */
    public function syncEggs(): int
    {
        $nests = $this->pelicanService->listNests();
        $eggCount = 0;

        foreach ($nests as $pelicanNest) {
            $nest = Nest::updateOrCreate(
                ['pelican_nest_id' => $pelicanNest->id],
                [
                    'name' => $pelicanNest->name,
                    'description' => $pelicanNest->description,
                ],
            );

            $eggs = $this->pelicanService->listEggs($pelicanNest->id);

            foreach ($eggs as $pelicanEgg) {
                Egg::updateOrCreate(
                    ['pelican_egg_id' => $pelicanEgg->id],
                    [
                        'nest_id' => $nest->id,
                        'name' => $pelicanEgg->name,
                        'docker_image' => $pelicanEgg->dockerImage,
                        'startup' => $pelicanEgg->startup,
                        'description' => $pelicanEgg->description,
                    ],
                );
                $eggCount++;
            }
        }

        return $eggCount;
    }

    // -------------------------------------------------------------------------
    // Nodes
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Health check
    // -------------------------------------------------------------------------

    /**
     * Verify consistency of all mappings between Pelican and the local database.
     *
     * @return array{users: SyncComparison, servers: SyncComparison, nodes_synced: int, eggs_synced: int}
     */
    public function healthCheck(): array
    {
        $userComparison = $this->compareUsers();
        $serverComparison = $this->compareServers();

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
