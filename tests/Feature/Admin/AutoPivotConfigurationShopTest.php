<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ServerConfiguration;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the auto-attach invariant : creating a `ServerConfiguration`
 * pivots it into every existing `Shop`, and creating a `Shop` pivots
 * it into every existing `ServerConfiguration`. Admins can detach
 * specific pairs afterwards ; the default is "everything visible
 * everywhere".
 */
class AutoPivotConfigurationShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_configuration_pivots_into_every_existing_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $config = ServerConfiguration::factory()->create();

        $this->assertTrue($shopA->serverConfigurations->contains($config));
        $this->assertTrue($shopB->serverConfigurations->contains($config));
    }

    public function test_creating_a_shop_pivots_every_existing_configuration_into_it(): void
    {
        $config1 = ServerConfiguration::factory()->create();
        $config2 = ServerConfiguration::factory()->create();
        $config3 = ServerConfiguration::factory()->create();

        $shop = Shop::factory()->create();

        $attached = $shop->serverConfigurations()->pluck('server_configurations.id');
        $this->assertTrue($attached->contains($config1->id));
        $this->assertTrue($attached->contains($config2->id));
        $this->assertTrue($attached->contains($config3->id));
    }

    public function test_pivot_default_is_visible_true(): void
    {
        $shop = Shop::factory()->create();
        $config = ServerConfiguration::factory()->create();

        $pivot = $shop->serverConfigurations()->where('server_configurations.id', $config->id)->first()?->pivot;
        $this->assertNotNull($pivot);
        $this->assertSame(1, (int) $pivot->is_visible);
    }

    public function test_re_creation_does_not_throw_on_unique_constraint(): void
    {
        // Idempotency : the auto-pivot uses syncWithoutDetaching, so
        // no double rows even if the same pair is touched twice.
        $shop = Shop::factory()->create();
        $config = ServerConfiguration::factory()->create();

        // Manually re-attach with different visibility — should not crash
        // and should not create a duplicate pivot row.
        $shop->serverConfigurations()->syncWithoutDetaching([$config->id => ['is_visible' => false]]);

        $this->assertSame(1, $shop->serverConfigurations()->where('server_configurations.id', $config->id)->count());
    }
}
