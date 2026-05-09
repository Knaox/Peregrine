<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Actions\Shops\AuthorizeConfigurationForShopAction;
use App\Actions\Shops\GenerateShopApiKeyAction;
use App\Models\Server;
use App\Models\ServerConfiguration;
use App\Models\Shop;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks the shape of the public API v1 surface :
 *  - /health is unauthenticated
 *  - /shop/me / /configurations / /orders / /webhooks all require Bearer
 *  - per-ability gating returns 403 with a clean error payload
 *  - cursor pagination + scoping + 404s on out-of-scope resources
 *  - signing_secret returned ONCE on POST /webhooks/endpoints
 */
class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_shop_me_returns_caller_shop(): void
    {
        [$shop, $token] = $this->seedShopWithKey(['configurations:read']);

        $this->withToken($token)
            ->getJson('/api/v1/shop/me')
            ->assertOk()
            ->assertJsonPath('data.id', $shop->id)
            ->assertJsonPath('data.slug', $shop->slug);
    }

    public function test_configurations_index_scoped_to_shop(): void
    {
        [$shop, $token] = $this->seedShopWithKey(['configurations:read']);

        $mine = $this->configuration();
        (new AuthorizeConfigurationForShopAction())($shop, $mine);

        $other = $this->configuration();
        $otherShop = Shop::factory()->create();
        (new AuthorizeConfigurationForShopAction())($otherShop, $other);

        $response = $this->withToken($token)->getJson('/api/v1/configurations');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_configuration_show_returns_404_when_not_in_pivot(): void
    {
        [$shop, $token] = $this->seedShopWithKey(['configurations:read']);
        $orphan = $this->configuration();

        $this->withToken($token)
            ->getJson('/api/v1/configurations/'.$orphan->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'configuration_not_found');
    }

    public function test_configurations_index_rejects_token_without_ability(): void
    {
        [, $token] = $this->seedShopWithKey(['orders:read']); // missing configurations:read

        $this->withToken($token)->getJson('/api/v1/configurations')->assertStatus(403);
    }

    public function test_orders_show_scoped_to_shop(): void
    {
        [$shop, $token] = $this->seedShopWithKey(['orders:read']);
        $config = $this->configuration();
        (new AuthorizeConfigurationForShopAction())($shop, $config);
        $user = User::factory()->create();
        $server = Server::create([
            'user_id' => $user->id,
            'pelican_server_id' => 1,
            'identifier' => 'iden123',
            'name' => 'srv',
            'status' => 'active',
            'server_configuration_id' => $config->id,
            'external_order_id' => 'ord-XYZ',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/orders/ord-XYZ')
            ->assertOk()
            ->assertJsonPath('data.external_order_id', 'ord-XYZ')
            ->assertJsonPath('data.server.id', $server->id);
    }

    public function test_orders_show_returns_404_for_other_shops_order(): void
    {
        [, $token] = $this->seedShopWithKey(['orders:read']);
        $config = $this->configuration();
        $otherShop = Shop::factory()->create();
        (new AuthorizeConfigurationForShopAction())($otherShop, $config);
        $user = User::factory()->create();
        Server::create([
            'user_id' => $user->id,
            'pelican_server_id' => 2,
            'identifier' => 'iden456',
            'name' => 'srv',
            'status' => 'active',
            'server_configuration_id' => $config->id,
            'external_order_id' => 'ord-ALIEN',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/orders/ord-ALIEN')
            ->assertStatus(404);
    }

    public function test_create_webhook_endpoint_returns_secret_once(): void
    {
        [$shop, $token] = $this->seedShopWithKey(['webhooks:read', 'webhooks:write']);

        $response = $this->withToken($token)->postJson('/api/v1/webhooks/endpoints', [
            'name' => 'CI',
            'url' => 'https://shop.example.com/webhooks/peregrine',
            'subscribed_events' => ['configuration.created', 'configuration.updated'],
        ])->assertCreated();

        $secret = $response->json('meta.signing_secret');
        $this->assertNotNull($secret);
        $this->assertStringStartsWith('whsec_', $secret);

        // Subsequent index does NOT expose the secret.
        $list = $this->withToken($token)->getJson('/api/v1/webhooks/endpoints')->assertOk();
        $this->assertArrayNotHasKey('signing_secret', $list->json('data.0'));
    }

    public function test_rotate_secret_updates_endpoint_and_returns_new_value(): void
    {
        [$shop, $token] = $this->seedShopWithKey(['webhooks:write']);
        $endpoint = WebhookEndpoint::factory()->for($shop)->create();
        $oldSecret = $endpoint->fresh()->signing_secret;

        $response = $this->withToken($token)
            ->postJson('/api/v1/webhooks/endpoints/'.$endpoint->id.'/rotate-secret');
        $response->assertOk();

        $newSecret = $response->json('meta.signing_secret');
        $this->assertNotNull($newSecret);
        $this->assertNotSame($oldSecret, $newSecret);
        $this->assertSame($newSecret, $endpoint->fresh()->signing_secret);
    }

    public function test_url_safety_blocks_loopback(): void
    {
        [, $token] = $this->seedShopWithKey(['webhooks:write']);

        $this->withToken($token)->postJson('/api/v1/webhooks/endpoints', [
            'name' => 'CI',
            'url' => 'https://localhost/hook',
            'subscribed_events' => ['configuration.updated'],
        ])->assertStatus(422);
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array{0: Shop, 1: string}
     */
    private function seedShopWithKey(array $abilities): array
    {
        $shop = Shop::factory()->create();
        $result = (new GenerateShopApiKeyAction())($shop, label: 'CI', abilities: $abilities);
        return [$shop, $result['plaintext']];
    }

    private function configuration(): ServerConfiguration
    {
        return ServerConfiguration::create([
            'internal_name' => 'cfg-api-'.Str::random(6),
            'name_template' => '{user.username}-{configuration.internal_name}',
            'ram' => 1024,
            'cpu' => 100,
            'disk' => 5000,
        ]);
    }
}
