<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per Bridge API call received from a third-party shop. Written
 * inline by the configuration sync controller after every signature-
 * validated request — successful or failed (validation error, controller
 * exception). Used for the `/admin/bridge-sync-logs` Filament audit page.
 *
 * No `timestamps()` — we only care about `attempted_at` (set by the
 * controller).
 */
class BridgeSyncLog extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'action',
        'shop_plan_id',
        'server_configuration_id',
        'request_payload',
        'response_status',
        'response_body',
        'ip_address',
        'signature_valid',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'shop_plan_id' => 'integer',
            'server_configuration_id' => 'integer',
            'request_payload' => 'array',
            'response_status' => 'integer',
            'signature_valid' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }

    public function serverConfiguration(): BelongsTo
    {
        return $this->belongsTo(ServerConfiguration::class);
    }
}
