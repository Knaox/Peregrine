<?php

namespace App\Services\Sync;

use App\Models\Egg;
use App\Models\Nest;
use App\Models\Node;
use App\Models\Pelican\Allocation;
use App\Models\Pelican\Backup;
use App\Models\Pelican\Database as PelicanDatabase;
use App\Models\Pelican\DatabaseHost;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Pelican\PelicanBackupService;
use App\Services\Pelican\PelicanDatabaseService;
use Illuminate\Support\Carbon;

/**
 * One-method-per-resource Pelican mirror syncer. Used by the
 * `pelican:backfill-mirrors` command (idempotent bootstrap) and the
 * hourly reconciliation job. Each method returns the count of items
 * processed (or that would be processed in dry-run mode).
 *
 * Extracted from PelicanBackfillMirrors so the command stays under the
 * 300-line plafond CLAUDE.md and the syncer logic is reusable elsewhere.
 */
final class PelicanMirrorSyncer
{
    public function __construct(
        private readonly PelicanApplicationService $pelican,
        private readonly PelicanBackupService $backupService,
        private readonly PelicanDatabaseService $databaseService,
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

    public function syncBackups(bool $dryRun): int
    {
        $count = 0;
        foreach (Server::whereNotNull('identifier')->cursor() as $server) {
            try {
                $remote = $this->backupService->listBackups($server->identifier);
            } catch (\Throwable) {
                continue;
            }
            foreach ($remote as $row) {
                $attrs = $row['attributes'] ?? $row;
                $pelicanId = (int) ($attrs['id'] ?? 0);
                if ($pelicanId === 0) {
                    continue;
                }
                $count++;
                if ($dryRun) {
                    continue;
                }
                Backup::updateOrCreate(
                    ['pelican_backup_id' => $pelicanId],
                    [
                        'server_id' => $server->id,
                        'uuid' => (string) ($attrs['uuid'] ?? ''),
                        'name' => (string) ($attrs['name'] ?? 'backup'),
                        'is_successful' => (bool) ($attrs['is_successful'] ?? false),
                        'is_locked' => (bool) ($attrs['is_locked'] ?? false),
                        'checksum' => $attrs['checksum'] ?? null,
                        'bytes' => (int) ($attrs['bytes'] ?? 0),
                        'completed_at' => isset($attrs['completed_at']) ? Carbon::parse((string) $attrs['completed_at']) : null,
                        'pelican_created_at' => isset($attrs['created_at']) ? Carbon::parse((string) $attrs['created_at']) : null,
                    ],
                );
            }
        }
        return $count;
    }

    public function syncDatabases(bool $dryRun): int
    {
        $count = 0;
        foreach (Server::whereNotNull('identifier')->cursor() as $server) {
            try {
                $remote = $this->databaseService->listDatabases($server->identifier);
            } catch (\Throwable) {
                continue;
            }
            foreach ($remote as $row) {
                $attrs = $row['attributes'] ?? $row;
                $pelicanId = (int) ($attrs['id'] ?? 0);
                if ($pelicanId === 0) {
                    continue;
                }
                $count++;
                if ($dryRun) {
                    continue;
                }
                $hostId = null;
                if (isset($attrs['host']['id'])) {
                    $hostId = DatabaseHost::firstOrCreate(
                        ['pelican_database_host_id' => (int) $attrs['host']['id']],
                        [
                            'name' => (string) ($attrs['host']['address'] ?? 'host'),
                            'host' => (string) ($attrs['host']['address'] ?? ''),
                            'port' => (int) ($attrs['host']['port'] ?? 3306),
                            'username' => '',
                            'max_databases' => 0,
                        ],
                    )->id;
                }
                PelicanDatabase::updateOrCreate(
                    ['pelican_database_id' => $pelicanId],
                    [
                        'server_id' => $server->id,
                        'pelican_database_host_id' => $hostId,
                        'database' => (string) ($attrs['name'] ?? ''),
                        'username' => (string) ($attrs['username'] ?? ''),
                        'remote' => (string) ($attrs['connections_from'] ?? '%'),
                        'max_connections' => (int) ($attrs['max_connections'] ?? 0),
                    ],
                );
            }
        }
        return $count;
    }

    public function syncAllocations(bool $dryRun): int
    {
        $count = 0;
        foreach (Node::whereNotNull('pelican_node_id')->cursor() as $node) {
            try {
                // include=server fills the DTO's `serverId` so we can skip
                // unassigned allocations rather than mirror every free port
                // on the panel (the legacy behaviour that bloated the table).
                $remote = $this->pelican->listNodeAllocations((int) $node->pelican_node_id, includeServer: true);
            } catch (\Throwable) {
                continue;
            }
            foreach ($remote as $r) {
                if ($r->serverId === null) {
                    // Free port — never mirrored. Re-allocations populate
                    // the table later via the assigned-allocation webhook
                    // or the next backfill pass.
                    continue;
                }

                $serverLocalId = Server::where('pelican_server_id', $r->serverId)->value('id');
                if ($serverLocalId === null) {
                    // Server known to Pelican but not yet to us — wait for
                    // the server mirror to catch up first.
                    continue;
                }

                $count++;
                if ($dryRun) {
                    continue;
                }
                Allocation::updateOrCreate(
                    ['pelican_allocation_id' => $r->id],
                    [
                        'node_id' => $node->id,
                        'server_id' => $serverLocalId,
                        'ip' => $r->ip,
                        'ip_alias' => $r->ipAlias,
                        'port' => $r->port,
                        'notes' => $r->notes,
                        'is_locked' => false,
                    ],
                );
            }
        }
        return $count;
    }

    /**
     * Backfill the invitations plugin's subuser mirror (per-server, via
     * the Client API). Delegates to the dedicated backfiller — this method
     * exists so `pelican:backfill-mirrors --only=subusers` keeps working.
     */
    public function syncSubusers(bool $dryRun): int
    {
        if ($dryRun) {
            return 0;
        }

        $report = app(\App\Services\Mirror\SubuserMirrorBackfiller::class)->run();

        return (int) ($report['written'] ?? 0);
    }

    public function syncTransfers(bool $dryRun): int
    {
        // Pelican Application API does not expose a list endpoint for
        // server transfers — they're picked up via webhooks only.
        return 0;
    }
}
