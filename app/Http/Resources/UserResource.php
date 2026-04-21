<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'theme_mode' => $this->theme_mode ?? 'auto',
            'is_admin' => $this->is_admin,
            'pelican_user_id' => $this->pelican_user_id,
            'has_two_factor' => $this->resource->hasTwoFactor(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
