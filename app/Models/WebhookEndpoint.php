<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * URL Peregrine pushes signed events to. Owned by a `Shop`. The
 * `signing_secret` is encrypted at rest via Crypt::encryptString in a
 * mutator/accessor pair so callers always read the plaintext but the
 * DB row never holds it in clear.
 *
 * `subscribed_events` is the allowlist of event types this endpoint
 * accepts. The fan-out skips endpoints that don't subscribe to the
 * emitted event type.
 */
class WebhookEndpoint extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'name',
        'url',
        'signing_secret',
        'status',
        'subscribed_events',
        'max_retries',
        'timeout_seconds',
        'consecutive_failures',
        'last_delivery_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'shop_id' => 'integer',
            'subscribed_events' => 'array',
            'max_retries' => 'integer',
            'timeout_seconds' => 'integer',
            'consecutive_failures' => 'integer',
            'last_delivery_at' => 'datetime',
        ];
    }

    /**
     * Encrypt at rest. The plaintext flows in/out transparently — only
     * the DB row holds the encrypted form. Fallback to plaintext on
     * decryption failure (test fixtures, legacy rows) to avoid hard
     * crashes ; production rows always go through the mutator.
     */
    protected function signingSecret(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }
                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable) {
                    return $value;
                }
            },
            set: fn (?string $value): ?string => $value === null
                ? null
                : Crypt::encryptString($value),
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function subscribesTo(string $eventType): bool
    {
        $subs = $this->subscribed_events ?? [];
        return is_array($subs) && in_array($eventType, $subs, true);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
