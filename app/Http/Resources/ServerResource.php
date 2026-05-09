<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $access = $user ? $user->serverAccess($this->resource) : null;

        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
            'name' => $this->name,
            'status' => $this->status,
            'pelican_server_id' => $this->pelican_server_id,
            'egg' => $this->whenLoaded('egg', fn () => [
                'id' => $this->egg->id,
                'name' => $this->egg->name,
                'banner_image' => $this->egg->banner_image ? asset('storage/' . $this->egg->banner_image) : null,
            ]),
            'configuration' => $this->whenLoaded('serverConfiguration', fn () => [
                'id' => $this->serverConfiguration->id,
                'internal_name' => $this->serverConfiguration->internal_name,
                'ram' => $this->serverConfiguration->ram,
                'cpu' => $this->serverConfiguration->cpu,
                'disk' => $this->serverConfiguration->disk,
            ]),
            'role' => $access['role'] ?? null,
            'permissions' => $access === null
                ? null
                : ($access['role'] === 'owner' ? null : $access['permissions']),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
