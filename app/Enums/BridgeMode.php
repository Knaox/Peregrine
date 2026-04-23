<?php

namespace App\Enums;

/**
 * Bridge runtime mode — only one bridge backend can be active at a time.
 *
 * `shop_stripe` and `paymenter` are mutually exclusive: a single radio
 * selector in /admin/bridge-settings persists this enum to the `settings`
 * table (key `bridge_mode`) and the rest of the codebase decides feature
 * visibility from helpers on this enum, never from raw boolean settings.
 *
 * Legacy `bridge_enabled` boolean is kept as fallback for one release —
 * see BridgeModeService::current().
 */
enum BridgeMode: string
{
    case Disabled = 'disabled';
    case ShopStripe = 'shop_stripe';
    case Paymenter = 'paymenter';

    public function isActive(): bool
    {
        return $this !== self::Disabled;
    }

    public function isShopStripe(): bool
    {
        return $this === self::ShopStripe;
    }

    public function isPaymenter(): bool
    {
        return $this === self::Paymenter;
    }

    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Disabled',
            self::ShopStripe => 'Custom shop + Stripe',
            self::Paymenter => 'Paymenter (Pelican webhook driven)',
        };
    }
}
