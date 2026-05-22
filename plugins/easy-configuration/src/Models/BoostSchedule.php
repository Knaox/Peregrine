<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A scheduled boost (table `easy_config_boost_schedules`).
 *
 * @property int $server_id
 * @property string $template_id
 * @property float $multiplier
 * @property string $status
 * @property list<array{file_id: string, section: string|null, key: string, max_cap?: float|null, original_value?: string, boosted_value?: string}> $parameters
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
        'applied_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /** Pending or active — i.e. boosts that still occupy their parameters. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'active']);
    }
}
