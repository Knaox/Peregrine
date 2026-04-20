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
            'show_app_name' => $this->resource['show_app_name'] ?? true,
            'logo_height' => $this->resource['logo_height'] ?? 40,
            'logo_url' => $this->resource['logo_url'] ?? '/images/logo.webp',
            'logo_url_light' => $this->resource['logo_url_light'] ?? ($this->resource['logo_url'] ?? '/images/logo.webp'),
            'favicon_url' => $this->resource['favicon_url'] ?? '/images/favicon.ico',
            'header_links' => $this->resource['header_links'] ?? [],
        ];
    }
}
