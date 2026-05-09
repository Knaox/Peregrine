<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WebhookDelivery;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDeliveryAttempt>
 */
final class WebhookDeliveryAttemptFactory extends Factory
{
    protected $model = WebhookDeliveryAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_delivery_id' => WebhookDelivery::factory(),
            'attempt_number' => 1,
            'request_body' => json_encode(['demo' => true]),
            'http_status_code' => 200,
            'response_body' => 'OK',
            'response_time_ms' => 142,
            'attempted_at' => now(),
        ];
    }
}
