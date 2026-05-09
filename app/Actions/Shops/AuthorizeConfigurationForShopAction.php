<?php

declare(strict_types=1);

namespace App\Actions\Shops;

use App\Models\ServerConfiguration;
use App\Models\Shop;

/**
 * Attaches a `ServerConfiguration` to a `Shop` via the
 * `shop_server_configuration` pivot. Idempotent : re-running with the
 * same pair updates the pivot metadata in place (no duplicate row, the
 * pivot has a UNIQUE(shop_id, server_configuration_id) constraint).
 */
final class AuthorizeConfigurationForShopAction
{
    public function __invoke(
        Shop $shop,
        ServerConfiguration $configuration,
        ?string $shopExternalId = null,
        bool $isVisible = true,
        int $sortOrder = 0,
    ): void {
        $shop->serverConfigurations()->syncWithoutDetaching([
            $configuration->id => [
                'shop_external_id' => $shopExternalId,
                'is_visible' => $isVisible,
                'sort_order' => $sortOrder,
            ],
        ]);
    }
}
