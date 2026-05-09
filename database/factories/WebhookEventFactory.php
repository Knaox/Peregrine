<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
final class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_type' => 'configuration.updated',
            'idempotency_key' => (string) Str::uuid(),
            'aggregate_type' => 'ServerConfiguration',
            'aggregate_id' => 1,
            'payload' => ['demo' => true],
            'emitted_at' => now(),
            'created_at' => now(),
        ];
    }
}
