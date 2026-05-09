<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
final class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'webhook_event_id' => WebhookEvent::factory(),
            'status' => 'pending',
            'attempt_count' => 0,
        ];
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => 'success',
            'attempt_count' => 1,
            'last_status_code' => 200,
            'first_attempted_at' => now(),
            'last_attempted_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'attempt_count' => 1,
            'last_status_code' => 500,
            'last_error_message' => 'simulated failure',
            'first_attempted_at' => now(),
            'last_attempted_at' => now(),
        ]);
    }
}
