<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bearer API key minted for a `Shop`. Plaintext token is shown once at
 * creation (Filament modal) and never persisted ; only the SHA-256 hash
 * is stored.
 *
 * Format of the plaintext token : `psk_<env>_<48 hex>` where env is
 * `live` or `test`. The prefix lets ops distinguish prod from sandbox
 * tokens at a glance even when one leaks into a log.
 *
 * Lifecycle :
 *  - `revoked_at` non-null : middleware rejects with 401.
 *  - `expires_at` set and elapsed : middleware rejects with 401.
 *  - Otherwise : middleware accepts, updates `last_used_at` /
 *    `last_used_ip` (deferred so it doesn't slow the request).
 *
 * `abilities` is a JSON array of scope strings such as
 * `"configurations:read"`. An empty/null array = no abilities (read-only
 * `/health` and `/shop/me` still allowed by middleware-level pass).
 */
class ShopApiKey extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'label',
        'key_prefix',
        'key_hash',
        'key_last4',
        'abilities',
        'expires_at',
        'last_used_at',
        'last_used_ip',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'shop_id' => 'integer',
            'abilities' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Returns true if the key is currently usable :
     *  - not revoked
     *  - not expired (or no expiration set)
     */
    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    /**
     * Constant-time check for a given ability string. Returns true also
     * when no abilities are declared on health-check / self endpoints —
     * caller decides which routes require an ability vs none.
     *
     * @param  array<int, string>  $required  any-of semantics
     */
    public function hasAnyAbility(array $required): bool
    {
        if ($required === []) {
            return true;
        }
        $owned = $this->abilities ?? [];
        if (! is_array($owned) || $owned === []) {
            return false;
        }
        foreach ($required as $needle) {
            if (in_array($needle, $owned, true)) {
                return true;
            }
        }
        return false;
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
