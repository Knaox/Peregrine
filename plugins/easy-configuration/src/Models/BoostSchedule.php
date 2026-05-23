<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A scheduled boost (table `easy_config_boost_schedules`).
 *
 * @property int $server_id
 * @property string $template_id
 * @property float $multiplier
 * @property string $status
 * @property string|null $recurrence daily|weekly|monthly, or null for a one-shot boost.
 * @property Carbon|null $recurrence_until null = repeat indefinitely.
 * @property list<array{file_id: string, section: string|null, key: string, max_cap?: float|null, invert?: bool, original_value?: string, boosted_value?: string}> $parameters
 */
class BoostSchedule extends Model
{
    protected $table = 'easy_config_boost_schedules';

    protected $fillable = [
        'server_id',
        'template_id',
        'multiplier',
        'start_at',
        'end_at',
        'status',
        'recurrence',
        'recurrence_until',
        'parameters',
        'applied_at',
        'ended_at',
        'created_by',
        'last_error',
    ];

    protected $casts = [
        'multiplier' => 'float',
        'parameters' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'recurrence_until' => 'datetime',
        'applied_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Pending, active, or cancelling — boosts that still occupy their parameters
     * (a cancelling boost is mid-restore, so it must keep them reserved and stay
     * listed until the job deletes it).
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'active', 'cancelling']);
    }
}
