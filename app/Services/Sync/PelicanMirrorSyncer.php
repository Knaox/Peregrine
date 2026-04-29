<?php

namespace App\Services\Sync;

use App\Models\Egg;
use App\Models\Nest;
use App\Models\Node;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;

/**
 * One-method-per-resource Pelican mirror syncer for the four CORE
 * resources (users, nodes, eggs, servers). Called by the
 * `pelican:backfill-mirrors` command — idempotent, dry-run capable.
 *
 * Per-server resources (backups / databases / allocations / subusers)
 * are NOT mirrored anymore : the SPA reads them live from Pelican on
 * demand (the "Lecture DB locale" feature was rolled back).
 */
final class PelicanMirrorSyncer
{
    public function __construct(
        private readonly PelicanApplicationService $pelican,
        private readonly UserSync $userSync,
        private readonly ServerSync $serverSync,
    ) {}

    public function syncUsers(bool $dryRun): int
    {
        $comparison = $this->userSync->compareUsers();
        $newPelicanIds = array_map(fn ($u) => $u->id, $comparison->new);

        if ($dryRun) {
            return count($newPelicanIds);
        }
        return $this->userSync->importUsers($newPelicanIds);
    }

    public function syncNodes(bool $dryRun): int
    {
        $nodes = $this->pelican->listNodes();
        if ($dryRun) {
            return count($nodes);
        }
        $count = 0;
        foreach ($nodes as $remote) {
            Node::updateOrCreate(
                ['pelican_node_id' => $remote->id],
                [
                    'name' => $remote->name,
                    'fqdn' => $remote->fqdn,
                    'memory' => $remote->memory,
                    'disk' => $remote->disk,
                    'location' => $remote->location,
                ],
            );
            $count++;
        }
        return $count;
    }

    public function syncEggs(bool $dryRun): int
    {
        $eggs = $this->pelican->listEggs();
        if ($dryRun) {
            return count($eggs);
        }
        $count = 0;
        foreach ($eggs as $remote) {
            $nestId = null;
            if ($remote->nestId > 0) {
                $nest = Nest::updateOrCreate(
                    ['pelican_nest_id' => $remote->nestId],
                    ['name' => 'Nest #'.$remote->nestId],
                );
                $nestId = $nest->id;
            }
            Egg::updateOrCreate(
                ['pelican_egg_id' => $remote->id],
                [
                    'nest_id' => $nestId,
                    'name' => $remote->name,
                    'docker_image' => $remote->dockerImage,
                    'startup' => $remote->startup,
                    'description' => $remote->description,
                    'tags' => $remote->tags,
                    'features' => $remote->features,
                ],
            );
            $count++;
        }
        return $count;
    }

    public function syncServers(bool $dryRun): int
    {
        $comparison = $this->serverSync->compareServers();
        $imports = [];

        foreach ($comparison->new as $pelicanServer) {
            // Match the Pelican server's owner to a local user (by pelican_user_id).
            // Skip servers whose owner isn't synced yet — caller should run
            // syncUsers() before syncServers() (the backfill command does that).
            $localUserId = User::where('pelican_user_id', $pelicanServer->userId)->value('id');
            if ($localUserId === null) {
                continue;
            }
            $imports[] = [
                'pelican_server_id' => $pelicanServer->id,
                'user_id' => (int) $localUserId,
            ];
        }

        if ($dryRun) {
            return count($imports);
        }
        return $this->serverSync->importServers($imports);
    }

}
