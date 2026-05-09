<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Shops\GenerateShopApiKeyAction;
use App\Actions\Shops\RevokeShopApiKeyAction;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateShopApiKeyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_plaintext_token_with_psk_live_prefix(): void
    {
        $shop = Shop::factory()->create();
        $action = new GenerateShopApiKeyAction();

        $result = $action($shop, label: 'CI key', abilities: ['configurations:read']);

        $this->assertArrayHasKey('plaintext', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertStringStartsWith('psk_live_', $result['plaintext']);
        $this->assertSame(9 + 48, strlen($result['plaintext']));  // 'psk_live_' (9) + 48 hex
    }

    public function test_persists_only_the_hash_never_plaintext(): void
    {
        $shop = Shop::factory()->create();
        $action = new GenerateShopApiKeyAction();
        $result = $action($shop, label: 'L');

        $stored = $result['key']->fresh();

        $this->assertNotSame($result['plaintext'], $stored->key_hash);
        $this->assertSame(hash('sha256', $result['plaintext']), $stored->key_hash);
        $this->assertSame(64, strlen($stored->key_hash));
    }

    public function test_supports_test_environment_prefix(): void
    {
        $shop = Shop::factory()->create();
        $action = new GenerateShopApiKeyAction();
        $result = $action($shop, label: 'sandbox', env: 'test');

        $this->assertStringStartsWith('psk_test_', $result['plaintext']);
    }

    public function test_falls_back_to_live_for_unknown_env(): void
    {
        $shop = Shop::factory()->create();
        $action = new GenerateShopApiKeyAction();
        $result = $action($shop, label: 'L', env: 'staging');

        $this->assertStringStartsWith('psk_live_', $result['plaintext']);
    }

    public function test_revoke_action_sets_revoked_at_idempotent(): void
    {
        $shop = Shop::factory()->create();
        $action = new GenerateShopApiKeyAction();
        $result = $action($shop, label: 'L');
        $key = $result['key'];

        $revoked = (new RevokeShopApiKeyAction())($key);
        $this->assertNotNull($revoked->revoked_at);

        $first = $revoked->revoked_at;

        // Idempotent : second call doesn't change the timestamp.
        $again = (new RevokeShopApiKeyAction())($revoked);
        $this->assertTrue($first->equalTo($again->revoked_at));
    }

    public function test_hash_collisions_blocked_by_unique_constraint(): void
    {
        // Astronomically unlikely, but the unique constraint on key_hash
        // is the right safety net. Force a collision by directly using
        // the same plaintext hash twice.
        $shop = Shop::factory()->create();

        \App\Models\ShopApiKey::create([
            'shop_id' => $shop->id,
            'label' => 'first',
            'key_prefix' => 'psk_live_',
            'key_hash' => str_repeat('a', 64),
            'key_last4' => 'aaaa',
            'abilities' => [],
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\ShopApiKey::create([
            'shop_id' => $shop->id,
            'label' => 'second',
            'key_prefix' => 'psk_live_',
            'key_hash' => str_repeat('a', 64),
            'key_last4' => 'aaaa',
            'abilities' => [],
        ]);
    }
}
