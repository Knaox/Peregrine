<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable record of a "something dispatchable happened" event. One
 * row per emission ; N delivery rows per row (one per subscribed
 * endpoint of an authorised shop).
 *
 * `idempotency_key` is the value Peregrine surfaces as the Standard
 * Webhooks `webhook-id` header so receivers can dedupe.
 */
class WebhookEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_type',
        'idempotency_key',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'emitted_at',
        'processed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'aggregate_id' => 'integer',
            'payload' => 'array',
            'emitted_at' => 'datetime',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
