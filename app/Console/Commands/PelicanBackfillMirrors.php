<?php

namespace App\Console\Commands;

use App\Models\PelicanBackfillProgress;
use App\Services\Sync\PelicanMirrorSyncer;
use Illuminate\Console\Command;

/**
 * Backfills the four CORE Pelican mirror tables : users, nodes, eggs,
 * servers. These four are read by the Filament admin (lists, filters,
 * /admin/users, /admin/servers, /admin/nodes, /admin/eggs) so they
 * MUST be in sync with Pelican.
 *
 * The downstream resources (backups / databases / allocations /
 * transfers / subusers) are NOT backfilled here anymore : the SPA reads
 * them live from Pelican on demand, mirror tables are dormant. Storing
 * them just for the sake of mirroring would inflate the DB and obscure
 * audit trails.
 *
 * Idempotent, chunked, resumable (`--resume` after interruption).
 *
 * Order matters: nodes → users → eggs → servers (each step depends on
 * the previous via FKs).
 */
class PelicanBackfillMirrors extends Command
{
    protected $signature = 'pelican:backfill-mirrors
        {--resume : Resume from where the last run stopped}
        {--fresh : Reset progress and start over}
        {--only= : Backfill only one resource (users|nodes|eggs|servers)}
        {--dry-run : Count remote items but don\'t write anything}';

    protected $description = 'Backfill the four core Pelican mirror tables (users, nodes, eggs, servers)';

    // Strict dependency order — each step depends on the previous ones :
    //   1. Nodes   (no FK)
    //   2. Users   (no FK — required by servers' owner_id)
    //   3. Eggs    (no FK — required by servers' egg_id)
    //   4. Servers (FKs : owner_id → users, egg_id → eggs)
    private const RESOURCES = [
        'nodes' => 'syncNodes',
        'users' => 'syncUsers',
        'eggs' => 'syncEggs',
        'servers' => 'syncServers',
    ];

    public function handle(PelicanMirrorSyncer $syncer): int
    {
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
                $count = $syncer->{$method}($dryRun);
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

        if (! $dryRun && $only === null) {
            $this->info("\n<fg=green>✓ Backfill complete.</>");
        }

        $duration = round(microtime(true) - $totalStart, 2);
        $this->line("Total duration: {$duration}s");
        return self::SUCCESS;
    }
}
