<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\ServerConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-facing snapshot of a `ServerConfiguration` for shop API
 * consumers. Mirrors the outbound webhook payload format so receivers
 * can use a single deserializer.
 */
class ConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ServerConfiguration $c */
        $c = $this->resource;

        return [
            'id' => $c->id,
            'internal_name' => $c->internal_name,
            'name_template' => $c->name_template,
            'ram' => $c->ram,
            'cpu' => $c->cpu,
            'disk' => $c->disk,
            'swap_mb' => $c->swap_mb,
            'io_weight' => $c->io_weight,
            'cpu_pinning' => $c->cpu_pinning,
            'egg_id' => $c->egg_id,
            'nest_id' => $c->nest_id,
            'docker_image' => $c->docker_image,
            'port_count' => $c->port_count,
            'feature_limits' => [
                'allocations' => $c->feature_limits_allocations,
                'backups' => $c->feature_limits_backups,
                'databases' => $c->feature_limits_databases,
            ],
            'env_var_mapping' => $c->env_var_mapping,
            'pivot' => $c->pivot ? [
                'shop_external_id' => $c->pivot->shop_external_id ?? null,
                'is_visible' => (bool) ($c->pivot->is_visible ?? true),
                'sort_order' => (int) ($c->pivot->sort_order ?? 0),
            ] : null,
        ];
    }
}
