<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
final class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->words(2, true),
            'url' => 'https://shop.test/webhooks/peregrine/'.Str::random(8),
            'signing_secret' => 'whsec_'.bin2hex(random_bytes(24)),
            'status' => 'active',
            'subscribed_events' => [
                'configuration.created',
                'configuration.updated',
                'configuration.deleted',
            ],
            'max_retries' => 5,
            'timeout_seconds' => 30,
            'consecutive_failures' => 0,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => 'paused']);
    }

    /**
     * @param  array<int, string>  $events
     */
    public function subscribedTo(array $events): static
    {
        return $this->state(fn () => ['subscribed_events' => $events]);
    }
}
