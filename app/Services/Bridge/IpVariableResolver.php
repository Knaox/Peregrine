<?php

declare(strict_types=1);

namespace App\Services\Bridge;

use App\Models\Node;
use App\Models\ServerConfiguration;
use App\Services\Network\CloudflareDnsResolver;
use App\Services\Pelican\DTOs\PelicanAllocation;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the "IP variable" for a ServerConfiguration and merges it into the
 * environment map about to be sent to Pelican.
 *
 * Driven by three columns on ServerConfiguration :
 *  - ip_variable_enabled : when false, the environment is returned untouched.
 *  - ip_variable_name    : the egg `env_variable` key that receives the IP.
 *  - ip_variable_source  :
 *      'node_fqdn'        → resolve the node's FQDN to its public IP
 *      'allocation_alias' → resolve the default allocation's ip_alias
 *
 * Both sources run through CloudflareDnsResolver (DoH A-record lookup, with an
 * IP-literal short-circuit). When resolution yields nothing, the variable is
 * left at its egg default and a warning is logged — provisioning must never
 * fail on a DNS hiccup.
 *
 * Runs AFTER EnvironmentResolver so the IP overrides any same-named mapping.
 */
class IpVariableResolver
{
    public const SOURCE_NODE_FQDN = 'node_fqdn';

    public const SOURCE_ALLOCATION_ALIAS = 'allocation_alias';

    public function __construct(private readonly CloudflareDnsResolver $dns) {}

    /**
     * @param  array<string, scalar>  $environment
     * @return array<string, scalar>
     */
    public function apply(
        array $environment,
        ServerConfiguration $configuration,
        Node $node,
        PelicanAllocation $defaultAllocation,
    ): array {
        if (! $configuration->ip_variable_enabled) {
            return $environment;
        }

        $variable = trim((string) ($configuration->ip_variable_name ?? ''));
        if ($variable === '') {
            return $environment;
        }

        $hostname = $this->sourceHostname($configuration, $node, $defaultAllocation);
        if ($hostname === null || trim($hostname) === '') {
            Log::warning('IpVariableResolver: no source hostname, leaving variable at egg default', [
                'configuration_id' => $configuration->id,
                'variable' => $variable,
                'source' => $configuration->ip_variable_source,
            ]);

            return $environment;
        }

        $ip = $this->dns->resolve($hostname);
        if ($ip === null) {
            Log::warning('IpVariableResolver: could not resolve IP, leaving variable at egg default', [
                'configuration_id' => $configuration->id,
                'variable' => $variable,
                'hostname' => $hostname,
            ]);

            return $environment;
        }

        $environment[$variable] = $ip;

        return $environment;
    }

    private function sourceHostname(
        ServerConfiguration $configuration,
        Node $node,
        PelicanAllocation $defaultAllocation,
    ): ?string {
        return match ($configuration->ip_variable_source) {
            self::SOURCE_ALLOCATION_ALIAS => $defaultAllocation->ipAlias,
            // Explicit node_fqdn + any unknown/legacy value fall back to the
            // node FQDN — the safe default for the feature.
            default => $node->fqdn,
        };
    }
}
