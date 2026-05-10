<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Actions\Shops\AuthorizeConfigurationForShopAction;
use App\Jobs\Webhooks\DispatchWebhookDeliveryJob;
use App\Models\ServerConfiguration;
use App\Models\Shop;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Regression : the `shop_server_configuration` pivot is `cascadeOnDelete`
 * on `server_configuration_id`. Without a pre-cascade snapshot, the
 * `deleted` observer queried an already-empty pivot and emitted no
 * `configuration.deleted` webhook — `created` worked because the pivot
 * is intact at insert time, `updated` worked because the pivot survives
 * an UPDATE, but `delete` was silently dropped.
 *
 * The fix snapshots the recipient shops in `deleting()` (before the SQL
 * DELETE runs) and passes them to `EmitWebhookEventAction` via its
 * `$shopsOverride` parameter.
 */
class ServerConfigurationDeleteWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_emits_configuration_deleted_event_with_recipients(): void
    {
        Bus::fake();

        $shop = Shop::factory()->create();
        $config = $this->configuration();
        (new AuthorizeConfigurationForShopAction)($shop, $config);
        WebhookEndpoint::factory()->for($shop)->create();

        $config->delete();

        $event = WebhookEvent::query()
            ->where('event_type', 'configuration.deleted')
            ->where('aggregate_id', $config->id)
            ->first();

        $this->assertNotNull($event, 'configuration.deleted event must be persisted');
        $this->assertSame(1, WebhookDelivery::count(), 'one delivery should fan out to the subscribed shop');
        Bus::assertDispatchedTimes(DispatchWebhookDeliveryJob::class, 1);
    }

    public function test_delete_with_no_attached_shop_emits_event_but_no_delivery(): void
    {
        Bus::fake();

        $config = $this->configuration(); // orphan — no shop pivot

        $config->delete();

        $this->assertSame(
            1,
            WebhookEvent::where('event_type', 'configuration.deleted')->count(),
            'the event ledger still records the delete (audit trail)'
        );
        $this->assertSame(0, WebhookDelivery::count());
        Bus::assertNothingDispatched();
    }

    public function test_delete_skips_endpoints_not_subscribed_to_deleted(): void
    {
        Bus::fake();

        $shop = Shop::factory()->create();
        $config = $this->configuration();
        (new AuthorizeConfigurationForShopAction)($shop, $config);
        WebhookEndpoint::factory()
            ->for($shop)
            ->subscribedTo(['configuration.created', 'configuration.updated'])
            ->create();

        $config->delete();

        $this->assertSame(0, WebhookDelivery::count(), 'endpoint not subscribed to deleted should not receive');
        Bus::assertNothingDispatched();
    }

    public function test_delete_skips_suspended_shops(): void
    {
        Bus::fake();

        $activeShop = Shop::factory()->create();
        $suspendedShop = Shop::factory()->suspended()->create();
        $config = $this->configuration();
        (new AuthorizeConfigurationForShopAction)($activeShop, $config);
        (new AuthorizeConfigurationForShopAction)($suspendedShop, $config);
        WebhookEndpoint::factory()->for($activeShop)->create();
        WebhookEndpoint::factory()->for($suspendedShop)->create();

        $config->delete();

        $this->assertSame(1, WebhookDelivery::count(), 'only the active shop receives');
        Bus::assertDispatchedTimes(DispatchWebhookDeliveryJob::class, 1);
    }

    private function configuration(): ServerConfiguration
    {
        return ServerConfiguration::create([
            'internal_name' => 'cfg-delete-'.uniqid(),
            'name_template' => '{user.username}-{configuration.internal_name}',
            'ram' => 1024,
            'cpu' => 100,
            'disk' => 5000,
        ]);
    }
}
