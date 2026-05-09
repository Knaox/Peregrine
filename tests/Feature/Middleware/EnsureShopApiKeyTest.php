<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Actions\Shops\GenerateShopApiKeyAction;
use App\Http\Middleware\EnsureShopApiKey;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Locks the Bearer-token middleware contract on a stub route mounted
 * during setUp. We don't depend on the real /api/v1 surface yet
 * (Phase 5 wiring) — the middleware is library-grade, tests it in
 * isolation against a no-op closure.
 */
class EnsureShopApiKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register test routes inside an explicit api middleware group +
        // attach the middleware under test. Using ->middleware() AFTER
        // ->get() to avoid the chain ordering issue where the registrar's
        // middleware wasn't being inherited.
        Route::get('/api/test-shop-api-key/echo', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'shop_id' => $request->attributes->get('shop')->id,
                'key_id' => $request->attributes->get('apiKey')->id,
            ]);
        })->middleware([
            EnsureShopApiKey::class,
        ])->withoutMiddleware([
            \App\Http\Middleware\EnsureInstalled::class,
            \App\Http\Middleware\SetUserLocale::class,
        ]);

        Route::get('/api/test-shop-api-key/scoped', fn () => response()->json(['ok' => true]))
            ->middleware([
                EnsureShopApiKey::class.':configurations:read',
            ])
            ->withoutMiddleware([
                \App\Http\Middleware\EnsureInstalled::class,
                \App\Http\Middleware\SetUserLocale::class,
            ]);
    }

    public function test_rejects_missing_bearer_token_with_401(): void
    {
        $response = $this->getJson('/api/test-shop-api-key/echo');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'missing_bearer_token');
    }

    public function test_rejects_unknown_token_with_401(): void
    {
        $response = $this->getJson('/api/test-shop-api-key/echo', [
            'Authorization' => 'Bearer psk_live_'.str_repeat('a', 48),
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'invalid_api_key');
    }

    public function test_rejects_revoked_token_with_401(): void
    {
        $shop = Shop::factory()->create();
        $result = (new GenerateShopApiKeyAction())($shop, label: 'L');
        $result['key']->forceFill(['revoked_at' => now()])->save();

        $response = $this->getJson('/api/test-shop-api-key/echo', [
            'Authorization' => 'Bearer '.$result['plaintext'],
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'api_key_revoked_or_expired');
    }

    public function test_rejects_expired_token_with_401(): void
    {
        $shop = Shop::factory()->create();
        $result = (new GenerateShopApiKeyAction())($shop, label: 'L', expiresAt: now()->subDay());

        $response = $this->getJson('/api/test-shop-api-key/echo', [
            'Authorization' => 'Bearer '.$result['plaintext'],
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'api_key_revoked_or_expired');
    }

    public function test_rejects_suspended_shop_with_403(): void
    {
        $shop = Shop::factory()->suspended()->create();
        $result = (new GenerateShopApiKeyAction())($shop, label: 'L');

        $response = $this->getJson('/api/test-shop-api-key/echo', [
            'Authorization' => 'Bearer '.$result['plaintext'],
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'shop_suspended');
    }

    public function test_accepts_valid_token_and_resolves_shop(): void
    {
        $shop = Shop::factory()->create();
        $result = (new GenerateShopApiKeyAction())($shop, label: 'L');

        $response = $this->getJson('/api/test-shop-api-key/echo', [
            'Authorization' => 'Bearer '.$result['plaintext'],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'shop_id' => $shop->id,
            'key_id' => $result['key']->id,
        ]);
    }

    public function test_updates_last_used_at_after_request(): void
    {
        $shop = Shop::factory()->create();
        $result = (new GenerateShopApiKeyAction())($shop, label: 'L');

        $this->assertNull($result['key']->last_used_at);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->getJson('/api/test-shop-api-key/echo', [
                'Authorization' => 'Bearer '.$result['plaintext'],
            ])->assertStatus(200);

        $fresh = $result['key']->fresh();
        $this->assertNotNull($fresh->last_used_at);
        $this->assertSame('203.0.113.42', $fresh->last_used_ip);
    }

    public function test_rejects_when_required_ability_missing(): void
    {
        $shop = Shop::factory()->create();
        $result = (new GenerateShopApiKeyAction())(
            $shop,
            label: 'L',
            abilities: ['orders:read'],
        );

        $response = $this->getJson('/api/test-shop-api-key/scoped', [
            'Authorization' => 'Bearer '.$result['plaintext'],
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'insufficient_scope');
    }

    public function test_accepts_when_required_ability_present(): void
    {
        $shop = Shop::factory()->create();
        $result = (new GenerateShopApiKeyAction())(
            $shop,
            label: 'L',
            abilities: ['configurations:read', 'orders:read'],
        );

        $response = $this->getJson('/api/test-shop-api-key/scoped', [
            'Authorization' => 'Bearer '.$result['plaintext'],
        ]);

        $response->assertStatus(200);
    }
}
