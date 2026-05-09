<?php

declare(strict_types=1);

namespace Tests\Unit\Bridge\Stripe\Actions;

use App\Actions\Shops\AuthorizeConfigurationForShopAction;
use App\Bridge\Stripe\Actions\ResolveStripeMetadataAction;
use App\Bridge\Stripe\Exceptions\BridgeMetadataException;
use App\Models\ServerConfiguration;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveStripeMetadataActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_when_required_metadata_missing(): void
    {
        $action = new ResolveStripeMetadataAction();

        $this->expectException(BridgeMetadataException::class);
        $action(['peregrine_configuration_id' => '1']); // missing the rest
    }

    public function test_throws_when_shop_unknown(): void
    {
        $config = ServerConfiguration::create($this->validConfigAttributes());
        $action = new ResolveStripeMetadataAction();

        try {
            $action([
                'peregrine_configuration_id' => (string) $config->id,
                'peregrine_shop_id' => '99999',
                'peregrine_user_email' => 'a@b.com',
                'peregrine_external_order_id' => 'ord-1',
            ]);
            $this->fail('Expected BridgeMetadataException');
        } catch (BridgeMetadataException $e) {
            $this->assertSame('unknown_shop', $e->reason);
        }
    }

    public function test_throws_when_shop_suspended(): void
    {
        $shop = Shop::factory()->suspended()->create();
        $config = ServerConfiguration::create($this->validConfigAttributes());
        (new AuthorizeConfigurationForShopAction())($shop, $config);

        try {
            (new ResolveStripeMetadataAction())([
                'peregrine_configuration_id' => (string) $config->id,
                'peregrine_shop_id' => (string) $shop->id,
                'peregrine_user_email' => 'a@b.com',
                'peregrine_external_order_id' => 'ord-1',
            ]);
            $this->fail();
        } catch (BridgeMetadataException $e) {
            $this->assertSame('shop_suspended', $e->reason);
        }
    }

    public function test_throws_when_configuration_not_authorised_for_shop(): void
    {
        $shop = Shop::factory()->create();
        $config = ServerConfiguration::create($this->validConfigAttributes());
        // No pivot row.

        try {
            (new ResolveStripeMetadataAction())([
                'peregrine_configuration_id' => (string) $config->id,
                'peregrine_shop_id' => (string) $shop->id,
                'peregrine_user_email' => 'a@b.com',
                'peregrine_external_order_id' => 'ord-1',
            ]);
            $this->fail();
        } catch (BridgeMetadataException $e) {
            $this->assertSame('configuration_not_authorised_for_shop', $e->reason);
        }
    }

    public function test_resolves_full_context_when_authorised(): void
    {
        $shop = Shop::factory()->create();
        $config = ServerConfiguration::create($this->validConfigAttributes());
        (new AuthorizeConfigurationForShopAction())($shop, $config);

        $context = (new ResolveStripeMetadataAction())([
            'peregrine_configuration_id' => (string) $config->id,
            'peregrine_shop_id' => (string) $shop->id,
            'peregrine_user_email' => 'BUYER@example.com',
            'peregrine_external_order_id' => 'ord-42',
            'peregrine_metadata' => json_encode(['campaign' => 'summer']),
        ]);

        $this->assertSame($shop->id, $context->shop->id);
        $this->assertSame($config->id, $context->configuration->id);
        $this->assertSame('buyer@example.com', $context->userEmail);
        $this->assertSame('ord-42', $context->externalOrderId);
        $this->assertSame(['campaign' => 'summer'], $context->extraMetadata);
        $this->assertNull($context->serverIdForResubscribe);
    }

    /**
     * @return array<string, mixed>
     */
    private function validConfigAttributes(): array
    {
        return [
            'internal_name' => 'cfg-test-'.uniqid(),
            'name_template' => '{user.username}-{configuration.internal_name}',
            'ram' => 1024,
            'cpu' => 100,
            'disk' => 5000,
        ];
    }
}
