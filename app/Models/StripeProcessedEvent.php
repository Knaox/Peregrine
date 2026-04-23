<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger for Stripe webhooks. One row per Stripe event we've
 * accepted (success OR explicit failure recorded). The webhook controller
 * checks this table BEFORE dispatching any job — duplicates short-circuit
 * to a 200 OK with no side effect. Cleaned daily by
 * `php artisan stripe:clean-processed-events`.
 */
class StripeProcessedEvent extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id',
        'event_type',
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
            'processed_at' => 'datetime',
        ];
    }
}
