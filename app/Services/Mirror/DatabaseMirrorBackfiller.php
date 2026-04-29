<?php

namespace App\Services\Mirror;

use App\Services\Sync\PelicanMirrorSyncer;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the existing PelicanMirrorSyncer::syncDatabases()
 * loop. Adds error capture and structured logging so a failure on one
 * server doesn't kill the whole backfill silently.
 *
 * The underlying syncer already iterates per-server and tolerates
 * individual fetch failures with a `try/catch ... continue` — we only
 * need to surface its result count + any total exception.
 */
final class DatabaseMirrorBackfiller
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
            $report['processed'] = $this->syncer->syncDatabases(dryRun: false);
        } catch (\Throwable $e) {
            $report['errors']++;
            Log::error('DatabaseMirrorBackfiller: syncDatabases failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $report;
    }
}
