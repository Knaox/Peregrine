<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Webhooks\EmitWebhookEventAction;
use App\Models\ResourceTemplate;
use App\Webhooks\WebhookEventTypes;

/**
 * Fans out `configuration.updated` webhook events whenever a
 * `ResourceTemplate` is mutated. Each `ServerConfiguration` bound to
 * the template carries the template's specs in its public payload
 * (RAM/CPU/disk/…), so editing the template "edits" every bound
 * configuration from a shop subscriber's point of view.
 *
 * We don't emit on `created` (a fresh template by itself is invisible
 * to shops until at least one configuration is bound to it) and we
 * don't emit on `deleted` (the bound configs already get a separate
 * webhook through `nullOnDelete` followed by their own
 * `configuration.updated` whenever the admin re-binds them — emitting
 * one extra event per orphaned config would just fire false alarms).
 */
final class ResourceTemplateObserver
{
    public function __construct(
        private readonly EmitWebhookEventAction $emitter,
    ) {}

    public function updated(ResourceTemplate $template): void
    {
        // Cache the bound configurations once. Each fan-out reuses the
        // same payload-builder logic the configuration observer relies
        // on, so the wire format stays identical regardless of where
        // the change originated.
        $configs = $template->serverConfigurations()->get();
        if ($configs->isEmpty()) {
            return;
        }

        foreach ($configs as $configuration) {
            ($this->emitter)(
                WebhookEventTypes::CONFIGURATION_UPDATED,
                $configuration,
                $this->payload($configuration),
            );
        }
    }

    /**
     * Mirror of `ServerConfigurationObserver::payload()` — kept in sync
     * by hand so the two emit paths produce byte-identical envelopes.
     *
     * @return array<string, mixed>
     */
    private function payload(\App\Models\ServerConfiguration $c): array
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
