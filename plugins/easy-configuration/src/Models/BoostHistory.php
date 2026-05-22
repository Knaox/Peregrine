<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Archived boost (table `easy_config_boost_history`). Written when a boost ends.
 *
 * @property string $final_status
 */
class BoostHistory extends Model
{
    public $timestamps = false;

    protected $table = 'easy_config_boost_history';

    protected $fillable = [
        'server_id',
        'template_id',
        'multiplier',
        'start_at',
        'end_at',
        'final_status',
        'parameters',
        'applied_at',
        'ended_at',
        'created_by',
        'note',
        'created_at',
    ];

    protected $casts = [
        'multiplier' => 'float',
        'parameters' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'applied_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
