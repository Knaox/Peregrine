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
     */
    public static function latest(): ?self
    {
        return self::query()->orderByDesc('started_at')->first();
    }

    public static function isAnyRunning(): bool
    {
        return self::query()->where('state', self::STATE_RUNNING)->exists();
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
