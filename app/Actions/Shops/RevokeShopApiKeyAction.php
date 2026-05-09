<?php

declare(strict_types=1);

namespace App\Actions\Shops;

use App\Models\ShopApiKey;

/**
 * Soft-revokes an API key. The row stays in DB (audit trail) ; the
 * Bearer middleware rejects any key with non-null `revoked_at`.
 *
 * Idempotent : revoking an already-revoked key keeps the original
 * `revoked_at` timestamp unchanged so we know exactly when the kill
 * switch was flipped.
 */
final class RevokeShopApiKeyAction
{
    public function __invoke(ShopApiKey $key): ShopApiKey
    {
        if ($key->revoked_at !== null) {
            return $key;
        }
        $key->forceFill(['revoked_at' => now()])->save();
        return $key->fresh();
    }
}
