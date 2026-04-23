<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger for Pelican outgoing webhooks (Bridge Paymenter mode).
 *
 * One row per event Peregrine has accepted. The webhook controller checks
 * this table BEFORE dispatching any sync job — duplicate physical events
 * (Pelican re-emits the same Eloquent change after webhook reconfigure)
 * short-circuit to a 200 OK with no side effect.
 *
 * Cleaned daily by `php artisan pelican:clean-processed-events`. Pelican
 * never retries on its own, so a 24h retention is enough.
 */
class PelicanProcessedEvent extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'idempotency_hash';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'idempotency_hash',
        'event_type',
        'pelican_model_id',
        'payload_summary',
        'response_status',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_summary' => 'array',
            'response_status' => 'integer',
            'pelican_model_id' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
