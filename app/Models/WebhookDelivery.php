<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-endpoint shipping record for a `WebhookEvent`. One row per
 * (event, endpoint) pair. Aggregates retry attempts in
 * `webhook_delivery_attempts`.
 */
class WebhookDelivery extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'webhook_endpoint_id',
        'webhook_event_id',
        'status',
        'attempt_count',
        'next_retry_at',
        'last_status_code',
        'last_error_message',
        'first_attempted_at',
        'last_attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'webhook_endpoint_id' => 'integer',
            'webhook_event_id' => 'integer',
            'attempt_count' => 'integer',
            'next_retry_at' => 'datetime',
            'last_status_code' => 'integer',
            'first_attempted_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(WebhookDeliveryAttempt::class);
    }
}
