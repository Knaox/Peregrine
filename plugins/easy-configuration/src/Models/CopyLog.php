<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One copy operation's outcome for a single target server (table
 * `easy_config_copy_log`). Grouped by `batch_id` so the UI can poll a whole
 * copy and report per-server success/failure.
 *
 * @property string $batch_id
 * @property string $status
 */
class CopyLog extends Model
{
    protected $table = 'easy_config_copy_log';

    protected $fillable = [
        'batch_id',
        'source_server_id',
        'target_server_id',
        'status',
        'files',
        'params_count',
        'copied_boosts',
        'error',
        'created_by',
    ];

    protected $casts = [
        'files' => 'array',
        'params_count' => 'integer',
        'copied_boosts' => 'boolean',
    ];
}
