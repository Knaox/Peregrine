<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncHealthCommand extends Command
{
    protected $signature = 'sync:health';

    protected $description = 'Check sync health between Pelican and local database';

    public function handle(SyncService $syncService): int
    {
        try {
            $this->info('Running health check...');
            $report = $syncService->healthCheck();

            $this->newLine();
            $this->line('<fg=cyan>--- Users ---</>');
            $this->info(sprintf('  New:      %d', count($report['users']->new)));
            $this->info(sprintf('  Synced:   %d', count($report['users']->synced)));
            if (count($report['users']->orphaned) > 0) {
                $this->warn(sprintf('  Orphaned: %d', count($report['users']->orphaned)));
            } else {
                $this->info(sprintf('  Orphaned: %d', count($report['users']->orphaned)));
            }

            $this->newLine();
            $this->line('<fg=cyan>--- Servers ---</>');
            $this->info(sprintf('  New:      %d', count($report['servers']->new)));
            $this->info(sprintf('  Synced:   %d', count($report['servers']->synced)));
            if (count($report['servers']->orphaned) > 0) {
                $this->warn(sprintf('  Orphaned: %d', count($report['servers']->orphaned)));
            } else {
                $this->info(sprintf('  Orphaned: %d', count($report['servers']->orphaned)));
            }

            $this->newLine();
            $this->line('<fg=cyan>--- Nodes & Eggs ---</>');
            $this->info(sprintf('  Nodes synced: %d', $report['nodes_synced']));
            $this->info(sprintf('  Eggs synced:  %d', $report['eggs_synced']));

            $hasIssues = count($report['users']->new) > 0
                || count($report['users']->orphaned) > 0
                || count($report['servers']->new) > 0
                || count($report['servers']->orphaned) > 0;

            $this->newLine();
            if ($hasIssues) {
                $this->warn('Health check completed with issues. Run sync commands to resolve.');
            } else {
                $this->info('Health check passed. Everything is in sync.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Health check failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
