<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Third-party shop integration. Each `Shop` represents a reseller that
 * sells `ServerConfiguration` rows to its own customers and references
 * Peregrine via Stripe metadata + signed outbound webhooks.
 *
 * A shop is identified by :
 *  - `slug` (admin-edited, unique, used in URLs and in
 *    `metadata.peregrine_shop_id` references after lookup).
 *  - At least one `ShopApiKey` to authenticate Bearer requests against
 *    `/api/v1/*`.
 *
 * `status === 'suspended'` blocks every inbound (Bearer middleware) and
 * outbound (webhook fan-out skips) interaction without deleting any rows.
 *
 * Related entities :
 *  - `ShopApiKey` : auth credentials.
 *  - `WebhookEndpoint` : URLs Peregrine pushes signed events to (Phase 3).
 *  - `ServerConfiguration` (N:N) : the technical catalog the shop is
 *    authorised to resell, scoped via the `shop_server_configuration`
 *    pivot.
 */
class Shop extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'status',
        'created_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ShopApiKey::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    public function serverConfigurations(): BelongsToMany
    {
        return $this->belongsToMany(
            ServerConfiguration::class,
            'shop_server_configuration',
        )
            ->withPivot(['shop_external_id', 'is_visible', 'sort_order'])
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
