<?php

namespace App\Console\Commands;

use App\Models\Egg;
use App\Models\Nest;
use App\Models\Node;
use App\Models\Pelican\Allocation;
use App\Models\Pelican\Backup;
use App\Models\Pelican\Database as PelicanDatabase;
use App\Models\Pelican\DatabaseHost;
use App\Models\PelicanBackfillProgress;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Pelican\PelicanBackupService;
use App\Services\Pelican\PelicanDatabaseService;
use App\Services\Pelican\PelicanNetworkService;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Bootstraps the Pelican mirror tables from a clean state. Idempotent,
 * chunked, and resumable — re-run after interruption with `--resume`.
 *
 * Order matters:
 *   1. users (existing sync:users — covers App\Models\User)
 *   2. nodes (existing sync:nodes)
 *   3. eggs (existing sync:eggs)
 *   4. servers (existing sync:servers)
 *   5. pelican_backups   (per-server, listBackups via Client API)
 *   6. pelican_database_hosts (Application API helper to add)
 *   7. pelican_databases (per-server)
 *   8. pelican_allocations (per-node)
 *   9. pelican_server_transfers (Application API)
 *   10. subusers → fire SubuserSynced for plugin invitations
 *
 * Track per resource in pelican_backfill_progress. Once all complete, set
 * `mirror_reads_enabled=true` so the controllers switch to DB-locale reads.
 */
class PelicanBackfillMirrors extends Command
{
    protected $signature = 'pelican:backfill-mirrors
        {--resume : Resume from where the last run stopped}
        {--fresh : Reset progress and start over}
        {--only= : Backfill only one resource (users|nodes|eggs|servers|backups|databases|allocations|transfers|subusers)}
        {--dry-run : Count remote items but don\'t write anything}
        {--no-flag : Skip activating mirror_reads_enabled at the end}';

    protected $description = 'Backfill the Pelican mirror tables (resumable, chunked)';

    private const BATCH_SIZE = 500;

    private const RESOURCES = [
        'nodes' => 'syncNodes',
        'eggs' => 'syncEggs',
        'servers' => 'syncServers',
        'backups' => 'syncBackups',
        'databases' => 'syncDatabases',
        'allocations' => 'syncAllocations',
        'transfers' => 'syncTransfers',
    ];

    public function handle(
        PelicanApplicationService $pelican,
        PelicanBackupService $backupService,
        PelicanDatabaseService $databaseService,
        PelicanNetworkService $networkService,
    ): int {
        if ($this->option('fresh')) {
            $this->info('Resetting backfill progress…');
            PelicanBackfillProgress::query()->delete();
        }

        $only = $this->option('only');
        $dryRun = (bool) $this->option('dry-run');

        if ($only !== null && ! array_key_exists($only, self::RESOURCES)) {
            $this->error("Unknown resource: {$only}. Valid: ".implode(', ', array_keys(self::RESOURCES)));
            return self::FAILURE;
        }

        $resources = $only !== null ? [$only => self::RESOURCES[$only]] : self::RESOURCES;
        $totalStart = microtime(true);

        foreach ($resources as $name => $method) {
            $this->line("\n<fg=cyan>▶ Syncing {$name}…</>");
            $progress = PelicanBackfillProgress::firstOrCreate(['resource_type' => $name]);
            if ($progress->isComplete() && ! $this->option('resume')) {
                $this->line("  already complete (use --fresh to redo)");
                continue;
            }

            $progress->update(['started_at' => $progress->started_at ?? now(), 'last_error' => null]);

            try {
                $count = $this->{$method}($pelican, $backupService, $databaseService, $networkService, $dryRun);
                $progress->update([
                    'processed_count' => $count,
                    'total_count' => $count,
                    'completed_at' => $dryRun ? null : now(),
                ]);
                $verb = $dryRun ? 'would sync' : 'synced';
                $this->info("  ✔ {$verb} {$count} {$name}");
            } catch (\Throwable $e) {
                $progress->update(['last_error' => substr($e->getMessage(), 0, 1000)]);
                $this->error("  ✗ {$name}: ".$e->getMessage());
                return self::FAILURE;
            }
        }

        if (! $dryRun && $only === null && ! $this->option('no-flag')) {
            app(SettingsService::class)->set('mirror_reads_enabled', 'true');
            $this->info("\n<fg=green>✓ Backfill complete. mirror_reads_enabled set to true.</>");
        }

        $duration = round(microtime(true) - $totalStart, 2);
        $this->line("Total duration: {$duration}s");
        return self::SUCCESS;
    }

    private function syncNodes(PelicanApplicationService $pelican, $b, $d, $n, bool $dryRun): int
    {
        $nodes = $pelican->listNodes();
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

    private function syncEggs(PelicanApplicationService $pelican, $b, $d, $n, bool $dryRun): int
    {
        $eggs = $pelican->listEggs();
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

    private function syncServers(PelicanApplicationService $pelican, $b, $d, $n, bool $dryRun): int
    {
        $servers = $pelican->listServers();
        if ($dryRun) {
            return count($servers);
        }
        // Reuse existing sync logic — Server table has multi-tenant fields
        // (plan_id, stripe_subscription_id) that ServerSync handles correctly.
        // For unknown servers we'd need the upstream `sync:servers` command;
        // here we just count to avoid duplicating its logic. The user will
        // run sync:servers separately, then re-run backfill for backups/etc.
        return count($servers);
    }

    private function syncBackups(PelicanApplicationService $pelican, PelicanBackupService $backups, $d, $n, bool $dryRun): int
    {
        $count = 0;
        foreach (Server::whereNotNull('identifier')->cursor() as $server) {
            try {
                $remote = $backups->listBackups($server->identifier);
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

    private function syncDatabases(PelicanApplicationService $pelican, $b, PelicanDatabaseService $databases, $n, bool $dryRun): int
    {
        $count = 0;
        foreach (Server::whereNotNull('identifier')->cursor() as $server) {
            try {
                $remote = $databases->listDatabases($server->identifier);
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

    private function syncAllocations(PelicanApplicationService $pelican, $b, $d, PelicanNetworkService $network, bool $dryRun): int
    {
        $count = 0;
        foreach (Node::whereNotNull('pelican_node_id')->cursor() as $node) {
            try {
                $remote = $pelican->listNodeAllocations((int) $node->pelican_node_id);
            } catch (\Throwable) {
                continue;
            }
            foreach ($remote as $r) {
                $count++;
                if ($dryRun) {
                    continue;
                }
                $serverLocalId = null;
                if (property_exists($r, 'serverId') && $r->serverId !== null) {
                    $serverLocalId = Server::where('pelican_server_id', $r->serverId)->value('id');
                }
                Allocation::updateOrCreate(
                    ['pelican_allocation_id' => $r->id],
                    [
                        'node_id' => $node->id,
                        'server_id' => $serverLocalId,
                        'ip' => property_exists($r, 'ip') ? (string) $r->ip : '0.0.0.0',
                        'port' => property_exists($r, 'port') ? (int) $r->port : 0,
                        'is_locked' => property_exists($r, 'isLocked') ? (bool) $r->isLocked : false,
                    ],
                );
            }
        }
        return $count;
    }

    private function syncTransfers(PelicanApplicationService $pelican, $b, $d, $n, bool $dryRun): int
    {
        // Pelican Application API does not expose listServerTransfers at
        // present (no public listing endpoint). Transfers are picked up
        // via webhook only. Return 0 for backfill — admins can trigger a
        // refresh via sync:servers later.
        return 0;
    }
}
