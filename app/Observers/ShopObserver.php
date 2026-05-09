<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Shops\AuthorizeConfigurationForShopAction;
use App\Models\ServerConfiguration;
use App\Models\Shop;

/**
 * Auto-pivots a freshly-created shop against every existing
 * `ServerConfiguration` so the new shop sees the full catalog by
 * default. Admins remain free to toggle individual pivot visibility
 * afterwards.
 *
 * Mirror of the auto-attach logic on the configuration side
 * (`ServerConfigurationObserver::created()`). Together, the two
 * observers maintain the invariant : "every (shop, configuration)
 * pair has a pivot row" until an admin explicitly detaches one.
 */
final class ShopObserver
{
    public function __construct(
        private readonly AuthorizeConfigurationForShopAction $authorize,
    ) {}

    public function created(Shop $shop): void
    {
        foreach (ServerConfiguration::query()->get() as $configuration) {
            ($this->authorize)($shop, $configuration);
        }
    }
}
