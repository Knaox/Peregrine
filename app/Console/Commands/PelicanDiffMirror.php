<?php

namespace App\Console\Commands;

use App\Models\Egg;
use App\Models\Node;
use App\Models\Pelican\Backup;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Pelican\PelicanBackupService;
use Illuminate\Console\Command;

/**
 * Audits drift between Peregrine's local mirror tables and Pelican's
 * source-of-truth API. Read-only — does NOT mutate. Useful to investigate
 * a suspected sync issue (the SPA always reads live API now, but the
 * mirror is still fed by webhooks + reconciliation for audit).
 *
 *   php artisan pelican:diff-mirror users
 *   php artisan pelican:diff-mirror servers
 *   php artisan pelican:diff-mirror nodes
 *   php artisan pelican:diff-mirror eggs
 *   php artisan pelican:diff-mirror backups
 *   php artisan pelican:diff-mirror all
 */
class PelicanDiffMirror extends Command
{
    protected $signature = 'pelican:diff-mirror {resource=all : One of: users, servers, nodes, eggs, backups, all}';

    protected $description = 'Audit drift between local mirror tables and Pelican API (read-only)';

    public function handle(PelicanApplicationService $pelican, PelicanBackupService $backups): int
    {
        $resource = (string) $this->argument('resource');
        $resources = $resource === 'all'
            ? ['users', 'servers', 'nodes', 'eggs', 'backups']
            : [$resource];

        foreach ($resources as $r) {
            match ($r) {
                'users' => $this->diffUsers($pelican),
                'servers' => $this->diffServers($pelican),
                'nodes' => $this->diffNodes($pelican),
                'eggs' => $this->diffEggs($pelican),
                'backups' => $this->diffBackups($backups),
                default => $this->error("Unknown resource: {$r}"),
            };
        }

        return self::SUCCESS;
    }

    private function diffUsers(PelicanApplicationService $pelican): void
    {
        $remote = collect($pelican->listUsers())->keyBy(fn ($u) => $u->id);
        $local = User::whereNotNull('pelican_user_id')->pluck('email', 'pelican_user_id');

        $missing = $remote->keys()->diff($local->keys())->values();
        $orphaned = $local->keys()->diff($remote->keys())->values();

        $this->table(['Resource', 'Remote', 'Local', 'Missing', 'Orphaned'], [[
            'users', $remote->count(), $local->count(), $missing->count(), $orphaned->count(),
        ]]);
    }

    private function diffServers(PelicanApplicationService $pelican): void
    {
        $remote = collect($pelican->listServers())->keyBy(fn ($s) => $s->id);
        $local = Server::whereNotNull('pelican_server_id')->pluck('name', 'pelican_server_id');

        $missing = $remote->keys()->diff($local->keys())->values();
        $orphaned = $local->keys()->diff($remote->keys())->values();

        $this->table(['Resource', 'Remote', 'Local', 'Missing', 'Orphaned'], [[
            'servers', $remote->count(), $local->count(), $missing->count(), $orphaned->count(),
        ]]);
    }

    private function diffNodes(PelicanApplicationService $pelican): void
    {
        $remote = collect($pelican->listNodes())->keyBy(fn ($n) => $n->id);
        $local = Node::whereNotNull('pelican_node_id')->pluck('name', 'pelican_node_id');

        $missing = $remote->keys()->diff($local->keys())->values();
        $orphaned = $local->keys()->diff($remote->keys())->values();

        $this->table(['Resource', 'Remote', 'Local', 'Missing', 'Orphaned'], [[
            'nodes', $remote->count(), $local->count(), $missing->count(), $orphaned->count(),
        ]]);
    }

    private function diffEggs(PelicanApplicationService $pelican): void
    {
        $remote = collect($pelican->listEggs())->keyBy(fn ($e) => $e->id);
        $local = Egg::whereNotNull('pelican_egg_id')->pluck('name', 'pelican_egg_id');

        $missing = $remote->keys()->diff($local->keys())->values();
        $orphaned = $local->keys()->diff($remote->keys())->values();

        $this->table(['Resource', 'Remote', 'Local', 'Missing', 'Orphaned'], [[
            'eggs', $remote->count(), $local->count(), $missing->count(), $orphaned->count(),
        ]]);
    }

    private function diffBackups(PelicanBackupService $backups): void
    {
        $totalRemote = 0;
        $totalLocal = 0;
        $totalMissing = 0;

        foreach (Server::whereNotNull('identifier')->cursor() as $server) {
            try {
                $remote = collect($backups->listBackups($server->identifier));
            } catch (\Throwable) {
                continue;
            }
            $local = Backup::where('server_id', $server->id)->pluck('pelican_backup_id');

            $remoteIds = $remote->pluck('id');
            $missing = $remoteIds->diff($local)->count();

            $totalRemote += $remoteIds->count();
            $totalLocal += $local->count();
            $totalMissing += $missing;
        }

        $this->table(['Resource', 'Remote', 'Local', 'Missing'], [[
            'backups (all servers)', $totalRemote, $totalLocal, $totalMissing,
        ]]);
    }
}
