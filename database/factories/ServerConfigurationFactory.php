<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Egg;
use App\Models\Node;
use App\Models\ServerConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerConfiguration>
 */
final class ServerConfigurationFactory extends Factory
{
    protected $model = ServerConfiguration::class;

    /**
     * Default state : a minimal NOT-yet-ready-to-provision configuration.
     * Use the `withEgg` / `withNode` / `autoDeploy` states to add the
     * provisioning prerequisites.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $internal = 'cfg-'.fake()->unique()->numerify('######');

        return [
            'internal_name' => $internal,
            'technical_description' => null,
            'name_template' => '{user.username}-{configuration.internal_name}',
            'ram' => 2048,
            'cpu' => 100,
            'disk' => 10240,
            'swap_mb' => 0,
            'io_weight' => 500,
            'cpu_pinning' => null,
            'egg_id' => null,
            'nest_id' => null,
            'node_id' => null,
            'default_node_id' => null,
            'allowed_node_ids' => null,
            'auto_deploy' => false,
            'docker_image' => null,
            'port_count' => 1,
            'env_var_mapping' => null,
            'enable_oom_killer' => true,
            'start_on_completion' => true,
            'skip_install_script' => false,
            'dedicated_ip' => false,
            'feature_limits_databases' => 0,
            'feature_limits_backups' => 3,
            'feature_limits_allocations' => 1,
        ];
    }

    /**
     * Attach an existing Egg. The model's `booted()` hook auto-derives
     * `nest_id` from `egg_id` on save, so we never need to set it here.
     *
     * Egg / Node / Nest do not have factories yet — tests build them with
     * `Model::create([...])` and pass them explicitly to this state.
     */
    public function withEgg(Egg $egg): static
    {
        return $this->state(fn () => [
            'egg_id' => $egg->id,
        ]);
    }

    /**
     * Pin the configuration to a specific Node (legacy fixed-deployment
     * mode). Mutually exclusive with `autoDeploy`.
     */
    public function withNode(Node $node): static
    {
        return $this->state(fn () => [
            'node_id' => $node->id,
            'auto_deploy' => false,
            'allowed_node_ids' => null,
        ]);
    }

    /**
     * Enable auto_deploy with the supplied allowed nodes. Caller passes the
     * node IDs explicitly — `ProvisionServerJob::pickNode()` resolves the
     * best one at provisioning time.
     *
     * @param  list<int>  $nodeIds
     */
    public function autoDeploy(array $nodeIds): static
    {
        return $this->state(fn () => [
            'auto_deploy' => true,
            'allowed_node_ids' => $nodeIds,
            'node_id' => null,
        ]);
    }
}
