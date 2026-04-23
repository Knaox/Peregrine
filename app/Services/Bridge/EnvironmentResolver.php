<?php

namespace App\Services\Bridge;

use App\Models\ServerPlan;
use App\Services\Pelican\DTOs\PelicanAllocation;

/**
 * Computes the `environment` map sent to Pelican when provisioning a server.
 *
 * For each entry in $plan->env_var_mapping :
 *  - type=offset → variable_name = port at offset_value (0-indexed) of the
 *    allocated consecutive ports
 *  - type=random → variable_name = a random port from the allocated set
 *  - type=static → variable_name = static_value (literal passthrough)
 *
 * Variables present in $eggDefaults but NOT mapped by the plan keep their
 * default value. Mapped variables override the default.
 */
class EnvironmentResolver
{
    /**
     * @param  array<int, PelicanAllocation>  $allocatedPorts
     * @param  array<string, scalar>          $eggDefaults
     * @return array<string, scalar>
     */
    public function resolve(ServerPlan $plan, array $allocatedPorts, array $eggDefaults): array
    {
        $environment = $eggDefaults;
        $mapping = $plan->env_var_mapping ?? [];

        if (! is_array($mapping)) {
            return $environment;
        }

        foreach ($mapping as $entry) {
            if (! is_array($entry) || ! isset($entry['variable_name'], $entry['type'])) {
                continue;
            }

            $name = (string) $entry['variable_name'];
            $type = (string) $entry['type'];

            $value = match ($type) {
                'offset' => $this->resolveOffset($entry, $allocatedPorts),
                'random' => $this->resolveRandom($allocatedPorts),
                'static' => $entry['static_value'] ?? null,
                default => null,
            };

            if ($value !== null) {
                $environment[$name] = $value;
            }
        }

        return $environment;
    }

    /**
     * @param  array<string, mixed>           $entry
     * @param  array<int, PelicanAllocation>  $allocatedPorts
     */
    private function resolveOffset(array $entry, array $allocatedPorts): ?int
    {
        $offset = (int) ($entry['offset_value'] ?? 0);

        return $allocatedPorts[$offset]->port ?? null;
    }

    /**
     * @param  array<int, PelicanAllocation>  $allocatedPorts
     */
    private function resolveRandom(array $allocatedPorts): ?int
    {
        if ($allocatedPorts === []) {
            return null;
        }

        return $allocatedPorts[array_rand($allocatedPorts)]->port;
    }
}
