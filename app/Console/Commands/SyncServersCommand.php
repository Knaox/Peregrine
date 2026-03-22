<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Models\User;
use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncServersCommand extends Command
{
    protected $signature = 'sync:servers';

    protected $description = 'Sync servers from Pelican to local database';

    public function handle(SyncService $syncService): int
    {
        $log = SyncLog::create([
            'type' => 'servers',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $this->info('Fetching servers from Pelican...');
            $comparison = $syncService->compareServers();

            $this->info(sprintf(
                'Found: %d new, %d synced, %d orphaned',
                count($comparison->new),
                count($comparison->synced),
                count($comparison->orphaned),
            ));

            if (count($comparison->new) > 0) {
                $this->table(
                    ['Pelican ID', 'Name', 'Pelican User ID'],
                    array_map(fn (object $s): array => [$s->id, $s->name, $s->userId ?? 'N/A'], $comparison->new),
                );

                if ($this->confirm('Import all new servers?', true)) {
                    $adminUser = User::where('is_admin', true)->first();

                    if (! $adminUser) {
                        $this->error('No admin user found. Please create an admin user first.');

                        $log->update([
                            'status' => 'failed',
                            'completed_at' => now(),
                            'summary' => ['error' => 'No admin user found'],
                        ]);

                        return self::FAILURE;
                    }

                    $this->info("Assigning all new servers to admin: {$adminUser->email}");

                    $serverImports = array_map(
                        fn (object $s): array => [
                            'pelican_server_id' => $s->id,
                            'user_id' => $adminUser->id,
                        ],
                        $comparison->new,
                    );

                    $imported = $syncService->importServers($serverImports);
                    $this->info("Imported {$imported} servers.");
                }
            }

            if (count($comparison->orphaned) > 0) {
                $this->warn(sprintf(
                    '%d orphaned servers (in Peregrine but not in Pelican)',
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
