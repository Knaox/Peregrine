<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncUsersCommand extends Command
{
    protected $signature = 'sync:users';

    protected $description = 'Sync users from Pelican to local database';

    public function handle(SyncService $syncService): int
    {
        $log = SyncLog::create([
            'type' => 'users',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $this->info('Fetching users from Pelican...');
            $comparison = $syncService->compareUsers();

            $this->info(sprintf(
                'Found: %d new, %d synced, %d orphaned',
                count($comparison->new),
                count($comparison->synced),
                count($comparison->orphaned),
            ));

            if (count($comparison->new) > 0) {
                $this->table(
                    ['Pelican ID', 'Email', 'Username'],
                    array_map(fn (object $u): array => [$u->id, $u->email, $u->username], $comparison->new),
                );

                if ($this->confirm('Import all new users?', true)) {
                    $ids = array_map(fn (object $u): int => $u->id, $comparison->new);
                    $imported = $syncService->importUsers($ids);
                    $this->info("Imported {$imported} users.");
                }
            }

            if (count($comparison->orphaned) > 0) {
                $this->warn(sprintf(
                    '%d orphaned users (in Peregrine but not in Pelican)',
                    count($comparison->orphaned),
                ));
            }

            $log->update([
                'status' => 'completed',
                'completed_at' => now(),
                'summary' => [
                    'new' => count($comparison->new),
                    'synced' => count($comparison->synced),
                    'orphaned' => count($comparison->orphaned),
                ],
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
