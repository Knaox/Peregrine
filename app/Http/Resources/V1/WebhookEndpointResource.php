<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-facing webhook endpoint snapshot. The `signing_secret` is
 * NEVER exposed via this resource ; it's only returned ONCE in the
 * 201 Create response (or a Rotate response) inside a top-level
 * `meta.signing_secret` field. After that, only the prefix and last
 * digits are surfaced.
 */
class WebhookEndpointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WebhookEndpoint $e */
        $e = $this->resource;

        return [
            'id' => $e->id,
            'shop_id' => $e->shop_id,
            'name' => $e->name,
            'url' => $e->url,
            'status' => $e->status,
            'subscribed_events' => $e->subscribed_events,
            'max_retries' => $e->max_retries,
            'timeout_seconds' => $e->timeout_seconds,
            'consecutive_failures' => $e->consecutive_failures,
            'last_delivery_at' => $e->last_delivery_at?->toIso8601String(),
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
