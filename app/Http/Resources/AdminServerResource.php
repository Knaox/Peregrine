<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin variant of ServerResource: always includes the owner block so the
 * admin mode dashboard can render "server X owned by user Y" rows without
 * extra lookups.
 */
class AdminServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
            'name' => $this->name,
            'status' => $this->status,
            'pelican_server_id' => $this->pelican_server_id,
            'owner' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'egg' => $this->whenLoaded('egg', fn () => [
                'id' => $this->egg->id,
                'name' => $this->egg->name,
                'banner_image' => $this->egg->banner_image ? asset('storage/'.$this->egg->banner_image) : null,
            ]),
            'plan' => $this->whenLoaded('plan', fn () => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'ram' => $this->plan->ram,
                'cpu' => $this->plan->cpu,
                'disk' => $this->plan->disk,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
