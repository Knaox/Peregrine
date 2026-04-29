<?php

namespace App\Services\Mirror;

use App\Services\Sync\UserSync;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the existing UserSync that adds isolated logging
 * per batch — so the operator who reports "synchronise pas les users"
 * gets a precise reason in the logs (Pelican empty / API error / dataset
 * mismatch) rather than a silent zero count.
 *
 * Reuses the canonical `compareUsers()` + `importUsers()` pair to avoid
 * forking the matching logic (email match + pelican_user_id link).
 */
final class UserMirrorBackfiller
{
    public function __construct(
        private readonly UserSync $userSync,
    ) {}

    /**
     * @return array{processed:int,written:int,errors:int}
     */
    public function run(): array
    {
        $report = ['processed' => 0, 'written' => 0, 'errors' => 0];

        try {
            $comparison = $this->userSync->compareUsers();
        } catch (\Throwable $e) {
            $report['errors']++;
            Log::error('UserMirrorBackfiller: compareUsers failed', [
                'error' => $e->getMessage(),
            ]);

            return $report;
        }

        $newPelicanIds = array_map(static fn ($u) => $u->id, $comparison->new);
        $report['processed'] = count($comparison->new) + count($comparison->synced);

        if ($newPelicanIds === []) {
            Log::info('UserMirrorBackfiller: no new Pelican users to import', [
                'synced_count' => count($comparison->synced),
            ]);

            return $report;
        }

        try {
            $report['written'] = $this->userSync->importUsers($newPelicanIds);
        } catch (\Throwable $e) {
            $report['errors']++;
            Log::error('UserMirrorBackfiller: importUsers failed', [
                'pelican_ids_attempted' => $newPelicanIds,
                'error' => $e->getMessage(),
            ]);
        }

        return $report;
    }
}
