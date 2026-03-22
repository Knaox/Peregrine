<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'app_name' => $this->resource['app_name'] ?? 'Peregrine',
            'logo_url' => $this->resource['logo_url'] ?? '/images/logo.svg',
            'favicon_url' => $this->resource['favicon_url'] ?? '/images/favicon.svg',
        ];
    }
}
