<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable per-retry record. Keeps the request body that was actually
 * sent (forensic replay) plus response status, headers, body
 * (truncated) and observed latency.
 */
class WebhookDeliveryAttempt extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'webhook_delivery_id',
        'attempt_number',
        'request_body',
        'http_status_code',
        'response_headers',
        'response_body',
        'response_time_ms',
        'error_type',
        'error_message',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'webhook_delivery_id' => 'integer',
            'attempt_number' => 'integer',
            'http_status_code' => 'integer',
            'response_headers' => 'array',
            'response_time_ms' => 'integer',
            'attempted_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(WebhookDelivery::class, 'webhook_delivery_id');
    }
}
