<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit row for a phpMyAdmin launch or redeem. `created_at` only
 * (no updates), so UPDATED_AT is disabled.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $server_id
 * @property string|null $database_id
 * @property string $event
 * @property string|null $ip
 */
class PmaLaunchLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pma_launch_logs';

    protected $fillable = [
        'user_id',
        'server_id',
        'database_id',
        'event',
        'ip',
    ];
}
