<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * One row per "Activer la lecture DB locale" run.
 *
 * Created with state=running by EnableLocalDbReadJob, flipped to
 * completed/failed at the end of the orchestration. The Filament page
 * polls the latest row to drive the UI (button label / progress badge).
 */
class MirrorBackfillProgress extends Model
{
    public const STATE_RUNNING = 'running';
    public const STATE_COMPLETED = 'completed';
    public const STATE_FAILED = 'failed';

    protected $table = 'mirror_backfill_progress';

    /** @var list<string> */
    protected $fillable = [
        'state',
        'started_at',
        'completed_at',
        'report',
        'error',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'report' => 'array',
        ];
    }

    public static function startNew(): self
    {
        return self::create([
            'state' => self::STATE_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Most recent run — null on a fresh install with no backfill ever
     * launched. Drives the Filament UI state.
     *
     * Defensive against the table being absent on legacy deployments where
     * the migration `2025_01_01_000040_create_mirror_backfill_progress_table`
     * never ran (e.g. integration servers whose `migrations` ledger is out
     * of sync with the actual schema). The page degrades gracefully to the
     * "never run" state instead of crashing with a 500.
     */
    public static function latest(): ?self
    {
        try {
            return self::query()->orderByDesc('started_at')->first();
        } catch (\Illuminate\Database\QueryException $e) {
            if (self::isMissingTableException($e)) {
                return null;
            }
            throw $e;
        }
    }

    public static function isAnyRunning(): bool
    {
        try {
            return self::query()->where('state', self::STATE_RUNNING)->exists();
        } catch (\Illuminate\Database\QueryException $e) {
            if (self::isMissingTableException($e)) {
                return false;
            }
            throw $e;
        }
    }

    private static function isMissingTableException(\Illuminate\Database\QueryException $e): bool
    {
        // MySQL 1146, SQLite "no such table", PostgreSQL "undefined_table".
        return str_contains($e->getMessage(), "doesn't exist")
            || str_contains($e->getMessage(), 'no such table')
            || str_contains($e->getMessage(), 'undefined_table');
    }

    /**
     * @param array<string, mixed> $report
     */
    public function markCompleted(array $report): void
    {
        $this->forceFill([
            'state' => self::STATE_COMPLETED,
            'completed_at' => now(),
            'report' => $report,
        ])->save();
    }

    public function markFailed(Throwable $e): void
    {
        $this->forceFill([
            'state' => self::STATE_FAILED,
            'completed_at' => now(),
            'error' => mb_substr($e->getMessage(), 0, 2000),
        ])->save();
    }

    public function isRunning(): bool
    {
        return $this->state === self::STATE_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->state === self::STATE_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->state === self::STATE_FAILED;
    }
}
