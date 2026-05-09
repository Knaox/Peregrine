<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Shops\AuthorizeConfigurationForShopAction;
use App\Actions\Webhooks\EmitWebhookEventAction;
use App\Models\ServerConfiguration;
use App\Models\Shop;
use App\Webhooks\WebhookEventTypes;

/**
 * Emits Standard Webhooks events whenever a `ServerConfiguration` row
 * mutates. Catalog-only — no lifecycle events. The fan-out logic lives
 * in `EmitWebhookEventAction` (handles authorisation per shop/endpoint).
 *
 * `created` / `updated` / `deleted` map 1:1 to the canonical events
 * defined in `WebhookEventTypes`. Payloads are minimal but stable :
 * subscribers should treat them as authoritative state snapshots and
 * NOT depend on field ordering or extra keys (we may add fields).
 */
final class ServerConfigurationObserver
{
    public function __construct(
        private readonly EmitWebhookEventAction $emitter,
        private readonly AuthorizeConfigurationForShopAction $authorize,
    ) {}

    public function created(ServerConfiguration $configuration): void
    {
        // Auto-pivot every freshly-created configuration into every
        // existing shop with default visibility. Admins keep the
        // freedom to toggle `is_visible=false` on individual pivots
        // afterwards, but the default is "available everywhere"
        // (mirrors the implicit shop-equality assumption when the
        // catalog is single-tenant). Idempotent — re-attaching is a
        // no-op thanks to the UNIQUE(shop_id, configuration_id)
        // constraint on the pivot.
        foreach (Shop::query()->get() as $shop) {
            ($this->authorize)($shop, $configuration);
        }

        ($this->emitter)(
            WebhookEventTypes::CONFIGURATION_CREATED,
            $configuration,
            $this->payload($configuration),
        );
    }

    public function updated(ServerConfiguration $configuration): void
    {
        ($this->emitter)(
            WebhookEventTypes::CONFIGURATION_UPDATED,
            $configuration,
            $this->payload($configuration),
        );
    }

    public function deleted(ServerConfiguration $configuration): void
    {
        // On delete the model still has its attributes ; ship the final
        // snapshot so the receiver knows what was removed.
        ($this->emitter)(
            WebhookEventTypes::CONFIGURATION_DELETED,
            $configuration,
            $this->payload($configuration),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ServerConfiguration $c): array
    {
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
        ];
    }
}
