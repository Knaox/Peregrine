<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-facing order state. Backed by `Server.external_order_id` —
 * shops poll this endpoint to track the asynchronous provisioning
 * state without subscribing to a separate lifecycle webhook.
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Server $s */
        $s = $this->resource;

        return [
            'external_order_id' => $s->external_order_id,
            'status' => $s->status,
            'configuration_id' => $s->server_configuration_id,
            'server' => [
                'id' => $s->id,
                'identifier' => $s->identifier,
                'pelican_server_id' => $s->pelican_server_id,
                'name' => $s->name,
            ],
            'scheduled_deletion_at' => $s->scheduled_deletion_at?->toIso8601String(),
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }
}
