<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncEggsCommand extends Command
{
    protected $signature = 'sync:eggs';

    protected $description = 'Sync eggs and nests from Pelican to local database';

    public function handle(SyncService $syncService): int
    {
        $log = SyncLog::create([
            'type' => 'eggs',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $this->info('Syncing eggs from Pelican...');
            $count = $syncService->syncEggs();
            $this->info("Synced {$count} eggs.");

            $log->update([
                'status' => 'completed',
                'completed_at' => now(),
                'summary' => ['eggs_synced' => $count],
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
