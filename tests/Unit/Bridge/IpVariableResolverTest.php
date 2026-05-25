<?php

declare(strict_types=1);

namespace Tests\Unit\Bridge;

use App\Models\Node;
use App\Models\ServerConfiguration;
use App\Services\Bridge\IpVariableResolver;
use App\Services\Network\CloudflareDnsResolver;
use App\Services\Pelican\DTOs\PelicanAllocation;
use Mockery;
use Tests\TestCase;

class IpVariableResolverTest extends TestCase
{
    public function test_disabled_returns_environment_untouched(): void
    {
        $dns = Mockery::mock(CloudflareDnsResolver::class);
        $dns->shouldReceive('resolve')->never();

        $config = $this->configuration(enabled: false, name: 'SERVER_IP', source: IpVariableResolver::SOURCE_NODE_FQDN);

        $env = (new IpVariableResolver($dns))->apply(
            ['EXISTING' => 'x'],
            $config,
            $this->node('node.example.com'),
            $this->allocation(),
        );

        $this->assertSame(['EXISTING' => 'x'], $env);
    }

    public function test_node_fqdn_source_resolves_node_fqdn(): void
    {
        $dns = Mockery::mock(CloudflareDnsResolver::class);
        $dns->shouldReceive('resolve')->once()->with('node.example.com')->andReturn('198.51.100.20');

        $config = $this->configuration(enabled: true, name: 'SERVER_IP', source: IpVariableResolver::SOURCE_NODE_FQDN);

        $env = (new IpVariableResolver($dns))->apply([], $config, $this->node('node.example.com'), $this->allocation());

        $this->assertSame('198.51.100.20', $env['SERVER_IP']);
    }

    public function test_allocation_alias_source_resolves_allocation_alias(): void
    {
        $dns = Mockery::mock(CloudflareDnsResolver::class);
        $dns->shouldReceive('resolve')->once()->with('play.example.com')->andReturn('203.0.113.9');

        $config = $this->configuration(enabled: true, name: 'SERVER_IP', source: IpVariableResolver::SOURCE_ALLOCATION_ALIAS);

        $env = (new IpVariableResolver($dns))->apply(
            [],
            $config,
            $this->node('node.example.com'),
            $this->allocation(ipAlias: 'play.example.com'),
        );

        $this->assertSame('203.0.113.9', $env['SERVER_IP']);
    }

    public function test_overrides_existing_same_named_value(): void
    {
        $dns = Mockery::mock(CloudflareDnsResolver::class);
        $dns->shouldReceive('resolve')->andReturn('198.51.100.20');

        $config = $this->configuration(enabled: true, name: 'SERVER_IP', source: IpVariableResolver::SOURCE_NODE_FQDN);

        $env = (new IpVariableResolver($dns))->apply(
            ['SERVER_IP' => '127.0.0.1'],
            $config,
            $this->node('node.example.com'),
            $this->allocation(),
        );

        $this->assertSame('198.51.100.20', $env['SERVER_IP']);
    }

    public function test_unresolved_ip_leaves_variable_unset(): void
    {
        $dns = Mockery::mock(CloudflareDnsResolver::class);
        $dns->shouldReceive('resolve')->once()->andReturn(null);

        $config = $this->configuration(enabled: true, name: 'SERVER_IP', source: IpVariableResolver::SOURCE_NODE_FQDN);

        $env = (new IpVariableResolver($dns))->apply([], $config, $this->node('node.example.com'), $this->allocation());

        $this->assertArrayNotHasKey('SERVER_IP', $env);
    }

    public function test_empty_variable_name_is_noop(): void
    {
        $dns = Mockery::mock(CloudflareDnsResolver::class);
        $dns->shouldReceive('resolve')->never();

        $config = $this->configuration(enabled: true, name: null, source: IpVariableResolver::SOURCE_NODE_FQDN);

        $env = (new IpVariableResolver($dns))->apply(['A' => 'b'], $config, $this->node('node.example.com'), $this->allocation());

        $this->assertSame(['A' => 'b'], $env);
    }

    public function test_allocation_alias_missing_leaves_variable_unset(): void
    {
        $dns = Mockery::mock(CloudflareDnsResolver::class);
        // No hostname to resolve when the allocation carries no alias.
        $dns->shouldReceive('resolve')->never();

        $config = $this->configuration(enabled: true, name: 'SERVER_IP', source: IpVariableResolver::SOURCE_ALLOCATION_ALIAS);

        $env = (new IpVariableResolver($dns))->apply(
            [],
            $config,
            $this->node('node.example.com'),
            $this->allocation(ipAlias: null),
        );

        $this->assertArrayNotHasKey('SERVER_IP', $env);
    }

    private function configuration(bool $enabled, ?string $name, string $source): ServerConfiguration
    {
        $config = new ServerConfiguration;
        $config->ip_variable_enabled = $enabled;
        $config->ip_variable_name = $name;
        $config->ip_variable_source = $source;

        return $config;
    }

    private function node(string $fqdn): Node
    {
        $node = new Node;
        $node->fqdn = $fqdn;

        return $node;
    }

    private function allocation(?string $ipAlias = null): PelicanAllocation
    {
        return new PelicanAllocation(
            id: 1, ip: '10.0.0.1', ipAlias: $ipAlias, port: 25565, notes: null, assigned: false,
        );
    }
}
