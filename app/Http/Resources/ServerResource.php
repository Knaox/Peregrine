<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'pelican_server_id' => $this->pelican_server_id,
            'egg' => $this->whenLoaded('egg', fn () => [
                'id' => $this->egg->id,
                'name' => $this->egg->name,
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
