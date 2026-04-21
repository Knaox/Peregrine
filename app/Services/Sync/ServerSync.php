<?php

namespace App\Services\Sync;

use App\Models\Egg;
use App\Models\Server;
use App\Services\DTOs\SyncComparison;
use App\Services\Pelican\PelicanApplicationService;

/**
 * Server sync — compare + import servers from Pelican. Auto-syncs eggs
 * when a server references an egg that doesn't exist locally yet
 * (InfrastructureSync is injected for that fallback).
 */
class ServerSync
{
    public function __construct(
        private PelicanApplicationService $pelicanService,
        private InfrastructureSync $infrastructureSync,
    ) {}

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

            // Try to match the egg — may be null if eggs haven't been synced yet
            $eggId = Egg::where('pelican_egg_id', $pelicanServer->eggId)->value('id');

            // Auto-sync eggs if no match found — delegate to the infrastructure service.
            if ($eggId === null) {
                $this->infrastructureSync->syncEggs();
                $eggId = Egg::where('pelican_egg_id', $pelicanServer->eggId)->value('id');
            }

            $server = Server::create([
                'pelican_server_id' => $pelicanServer->id,
                'identifier' => $pelicanServer->identifier,
                'user_id' => $userId,
                'name' => $pelicanServer->name,
                'status' => $pelicanServer->isSuspended ? 'suspended' : 'active',
                'egg_id' => $eggId,
            ]);

            // Register the owner in the server_user pivot so the dashboard
            // (which queries via User::accessibleServers()) surfaces the
            // imported server. Without this row the server exists but is
            // invisible to everyone except admin Filament.
            $server->accessUsers()->syncWithoutDetaching([
                $userId => ['role' => 'owner', 'permissions' => null],
            ]);

            $imported++;
        }

        return $imported;
    }
}
