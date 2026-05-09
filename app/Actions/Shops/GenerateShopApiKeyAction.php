<?php

declare(strict_types=1);

namespace App\Actions\Shops;

use App\Models\Shop;
use App\Models\ShopApiKey;

/**
 * Mints a new API key for a `Shop`. Returns the plaintext token to the
 * caller — the caller MUST display it once and never store it. Only the
 * SHA-256 hash lives in DB.
 *
 * Token format : `psk_<env>_<48 hex>`. The `psk_` prefix is conventional
 * (Peregrine Secret Key) and lets ops grep secrets out of logs ; the env
 * (`live` / `test`) is a human signal — there's no server-side gating on
 * env yet.
 *
 * Idempotency : the action does NOT enforce per-shop label uniqueness ;
 * an admin can mint multiple keys with the same label (e.g. "ci-2026-q1"
 * twice) and revoke the older one. The `key_hash` UNIQUE constraint
 * blocks accidental duplicate plaintext (random_bytes collision is
 * astronomically unlikely but the constraint is the right belt-and-
 * braces guarantee).
 */
final class GenerateShopApiKeyAction
{
    /**
     * @param  array<int, string>  $abilities
     * @return array{key: ShopApiKey, plaintext: string}
     */
    public function __invoke(
        Shop $shop,
        string $label,
        array $abilities = [],
        ?\DateTimeInterface $expiresAt = null,
        string $env = 'live',
    ): array {
        $env = in_array($env, ['live', 'test'], true) ? $env : 'live';
        $body = bin2hex(random_bytes(24));
        $plaintext = "psk_{$env}_{$body}";

        $key = ShopApiKey::create([
            'shop_id' => $shop->id,
            'label' => $label,
            'key_prefix' => "psk_{$env}_",
            'key_hash' => hash('sha256', $plaintext),
            'key_last4' => substr($body, -4),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return [
            'key' => $key,
            'plaintext' => $plaintext,
        ];
    }
}
