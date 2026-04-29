<?php

namespace App\Services\Mirror;

use App\Services\Sync\PelicanMirrorSyncer;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the existing PelicanMirrorSyncer::syncBackups()
 * loop. Adds error capture and structured logging so a failure on one
 * server doesn't kill the whole backfill silently.
 *
 * Mirrors only what Pelican exposes — completed backups, names, sizes,
 * locked flag. Backup payloads themselves stay on Pelican (we never
 * download / cache the archive contents).
 */
final class BackupMirrorBackfiller
{
    public function __construct(
        private readonly PelicanMirrorSyncer $syncer,
    ) {}

    /**
     * @return array{processed:int,errors:int}
     */
    public function run(): array
    {
        $report = ['processed' => 0, 'errors' => 0];

        try {
            $report['processed'] = $this->syncer->syncBackups(dryRun: false);
        } catch (\Throwable $e) {
            $report['errors']++;
            Log::error('BackupMirrorBackfiller: syncBackups failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $report;
    }
}
