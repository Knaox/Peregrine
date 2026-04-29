<?php

namespace App\Console\Commands;

use App\Models\PelicanBackfillProgress;
use App\Services\SettingsService;
use App\Services\Sync\PelicanMirrorSyncer;
use Illuminate\Console\Command;

/**
 * Bootstraps the Pelican mirror tables from a clean state. Idempotent,
 * chunked, and resumable — re-run after interruption with `--resume`.
 *
 * Order matters: users → nodes → eggs → servers → backups → databases →
 * allocations → transfers. Each step delegates to PelicanMirrorSyncer.
 *
 * Once all complete, sets `mirror_reads_enabled=true` so the controllers
 * switch from API-live reads to DB-locale reads.
 */
class PelicanBackfillMirrors extends Command
{
    protected $signature = 'pelican:backfill-mirrors
        {--resume : Resume from where the last run stopped}
        {--fresh : Reset progress and start over}
        {--only= : Backfill only one resource (users|nodes|eggs|servers|backups|databases|allocations|transfers)}
        {--dry-run : Count remote items but don\'t write anything}
        {--no-flag : Skip activating mirror_reads_enabled at the end}';

    protected $description = 'Backfill the Pelican mirror tables (resumable, chunked)';

    // Strict dependency order — each step depends on the previous ones :
    //   1. Nodes      (no FK)
    //   2. Users      (no FK — required by servers' owner_id)
    //   3. Eggs       (no FK — required by servers' egg_id)
    //   4. Servers    (FKs : owner_id → users, egg_id → eggs)
    //   5. Backups    (FK : server_id → servers)
    //   6. Databases  (FK : server_id → servers)
    //   7. Allocations (FK : node_id → nodes, server_id nullable)
    //   8. Transfers  (FK : server_id → servers)
    private const RESOURCES = [
        'nodes' => 'syncNodes',
        'users' => 'syncUsers',
        'eggs' => 'syncEggs',
        'servers' => 'syncServers',
        'backups' => 'syncBackups',
        'databases' => 'syncDatabases',
        'allocations' => 'syncAllocations',
        'transfers' => 'syncTransfers',
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

        if (! $dryRun && $only === null && ! $this->option('no-flag')) {
            app(SettingsService::class)->set('mirror_reads_enabled', 'true');
            $this->info("\n<fg=green>✓ Backfill complete. mirror_reads_enabled set to true.</>");
        }

        $duration = round(microtime(true) - $totalStart, 2);
        $this->line("Total duration: {$duration}s");
        return self::SUCCESS;
    }
}
