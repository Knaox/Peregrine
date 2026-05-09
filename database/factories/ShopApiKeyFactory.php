<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShopApiKey>
 *
 * The factory generates a fresh plaintext + hash pair so tests that drive
 * the middleware end-to-end can grab the plaintext via `withPlaintext()`
 * (state) without round-tripping through `GenerateShopApiKeyAction`.
 */
final class ShopApiKeyFactory extends Factory
{
    protected $model = ShopApiKey::class;

    public ?string $plaintext = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $env = 'live';
        $body = bin2hex(random_bytes(24));
        $this->plaintext = "psk_{$env}_{$body}";

        return [
            'shop_id' => Shop::factory(),
            'label' => fake()->words(2, true),
            'key_prefix' => "psk_{$env}_",
            'key_hash' => hash('sha256', $this->plaintext),
            'key_last4' => substr($body, -4),
            'abilities' => ['configurations:read', 'orders:read'],
            'expires_at' => null,
            'last_used_at' => null,
            'last_used_ip' => null,
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    public function withAbilities(array $abilities): static
    {
        return $this->state(fn () => ['abilities' => $abilities]);
    }
}
