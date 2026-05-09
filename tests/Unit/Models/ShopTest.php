<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Shop;
use App\Models\ShopApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_active_shop(): void
    {
        $shop = Shop::factory()->create();

        $this->assertSame('active', $shop->status);
        $this->assertTrue($shop->isActive());
    }

    public function test_suspended_state(): void
    {
        $shop = Shop::factory()->suspended()->create();

        $this->assertSame('suspended', $shop->status);
        $this->assertFalse($shop->isActive());
    }

    public function test_shop_has_many_api_keys(): void
    {
        $shop = Shop::factory()->create();
        ShopApiKey::factory()->count(3)->for($shop)->create();

        $this->assertCount(3, $shop->apiKeys);
    }

    public function test_metadata_is_cast_to_array(): void
    {
        $shop = Shop::factory()->create(['metadata' => ['foo' => 'bar']]);

        $this->assertSame(['foo' => 'bar'], $shop->fresh()->metadata);
    }

    public function test_slug_must_be_unique(): void
    {
        Shop::factory()->create(['slug' => 'duplicate']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Shop::factory()->create(['slug' => 'duplicate']);
    }
}
