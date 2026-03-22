<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncNodesCommand extends Command
{
    protected $signature = 'sync:nodes';

    protected $description = 'Sync nodes from Pelican to local database';

    public function handle(SyncService $syncService): int
    {
        $log = SyncLog::create([
            'type' => 'nodes',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $this->info('Syncing nodes from Pelican...');
            $count = $syncService->syncNodes();
            $this->info("Synced {$count} nodes.");

            $log->update([
                'status' => 'completed',
                'completed_at' => now(),
                'summary' => ['nodes_synced' => $count],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());

            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'summary' => ['error' => $e->getMessage()],
            ]);

            return self::FAILURE;
        }
    }
}
