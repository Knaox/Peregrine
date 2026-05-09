<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Actions\Shops\AuthorizeConfigurationForShopAction;
use App\Actions\Webhooks\EmitWebhookEventAction;
use App\Jobs\Webhooks\DispatchWebhookDeliveryJob;
use App\Models\ServerConfiguration;
use App\Models\Shop;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class EmitWebhookEventActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_event_with_unique_idempotency_key(): void
    {
        Bus::fake();
        $config = $this->configuration();

        $event = (new EmitWebhookEventAction())(
            'configuration.updated',
            $config,
            ['demo' => true],
        );

        $this->assertInstanceOf(WebhookEvent::class, $event);
        $this->assertSame('configuration.updated', $event->event_type);
        $this->assertSame('ServerConfiguration', $event->aggregate_type);
        $this->assertSame($config->id, $event->aggregate_id);
        $this->assertNotNull($event->idempotency_key);
        $this->assertNotNull($event->processed_at);
    }

    public function test_orphan_configuration_emits_no_delivery(): void
    {
        Bus::fake();
        $config = $this->configuration(); // no shop attached

        (new EmitWebhookEventAction())('configuration.updated', $config, []);

        Bus::assertNothingDispatched();
        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_fans_out_per_shop_per_subscribed_endpoint(): void
    {
        Bus::fake();
        $config = $this->configuration();

        // Shop A : 2 endpoints, both subscribed → 2 deliveries
        $shopA = Shop::factory()->create();
        (new AuthorizeConfigurationForShopAction())($shopA, $config);
        WebhookEndpoint::factory()->for($shopA)->create();
        WebhookEndpoint::factory()->for($shopA)->create();

        // Shop B : 1 endpoint, but subscribed only to created → 0 deliveries
        $shopB = Shop::factory()->create();
        (new AuthorizeConfigurationForShopAction())($shopB, $config);
        WebhookEndpoint::factory()
            ->for($shopB)
            ->subscribedTo(['configuration.created'])
            ->create();

        // Shop C : suspended → 0 deliveries
        $shopC = Shop::factory()->suspended()->create();
        (new AuthorizeConfigurationForShopAction())($shopC, $config);
        WebhookEndpoint::factory()->for($shopC)->create();

        // Shop D : endpoint paused → 0 deliveries
        $shopD = Shop::factory()->create();
        (new AuthorizeConfigurationForShopAction())($shopD, $config);
        WebhookEndpoint::factory()->for($shopD)->paused()->create();

        (new EmitWebhookEventAction())('configuration.updated', $config, ['demo' => true]);

        $this->assertSame(2, WebhookDelivery::count(), 'only shop A endpoints should receive');
        Bus::assertDispatchedTimes(DispatchWebhookDeliveryJob::class, 2);
    }

    public function test_idempotency_key_is_unique_across_emissions(): void
    {
        Bus::fake();
        $config = $this->configuration();

        $first = (new EmitWebhookEventAction())('configuration.updated', $config, []);
        $second = (new EmitWebhookEventAction())('configuration.updated', $config, []);

        $this->assertNotSame($first->idempotency_key, $second->idempotency_key);
    }

    private function configuration(): ServerConfiguration
    {
        return ServerConfiguration::create([
            'internal_name' => 'cfg-emit-'.uniqid(),
            'name_template' => '{user.username}-{configuration.internal_name}',
            'ram' => 1024,
            'cpu' => 100,
            'disk' => 5000,
        ]);
    }
}
