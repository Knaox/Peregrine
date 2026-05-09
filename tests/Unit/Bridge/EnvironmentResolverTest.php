<?php

declare(strict_types=1);

namespace Tests\Unit\Bridge;

use App\Models\ServerConfiguration;
use App\Services\Bridge\EnvironmentResolver;
use App\Services\Pelican\DTOs\PelicanAllocation;
use Tests\TestCase;

class EnvironmentResolverTest extends TestCase
{
    public function test_resolves_offset_type_correctly(): void
    {
        $configuration = $this->configurationWithMapping([
            ['variable_name' => 'GAME_PORT', 'type' => 'offset', 'offset_value' => 0],
            ['variable_name' => 'TELNET_PORT', 'type' => 'offset', 'offset_value' => 1],
        ]);

        $ports = [
            $this->alloc(25565),
            $this->alloc(25566),
        ];

        $env = (new EnvironmentResolver())->resolve($configuration, $ports, []);

        $this->assertSame(25565, $env['GAME_PORT']);
        $this->assertSame(25566, $env['TELNET_PORT']);
    }

    public function test_resolves_static_type_passthrough(): void
    {
        $configuration = $this->configurationWithMapping([
            ['variable_name' => 'MAX_PLAYERS', 'type' => 'static', 'static_value' => '20'],
        ]);

        $env = (new EnvironmentResolver())->resolve($configuration, [], []);

        $this->assertSame('20', $env['MAX_PLAYERS']);
    }

    public function test_resolves_random_type_picks_one_of_allocated(): void
    {
        $configuration = $this->configurationWithMapping([
            ['variable_name' => 'QUERY_PORT', 'type' => 'random'],
        ]);

        $ports = [$this->alloc(25565), $this->alloc(25566), $this->alloc(25567)];

        $env = (new EnvironmentResolver())->resolve($configuration, $ports, []);

        $this->assertContains($env['QUERY_PORT'], [25565, 25566, 25567]);
    }

    public function test_fills_defaults_for_unmapped_egg_variables(): void
    {
        $configuration = $this->configurationWithMapping([
            ['variable_name' => 'GAME_PORT', 'type' => 'offset', 'offset_value' => 0],
        ]);

        $ports = [$this->alloc(25565)];
        $eggDefaults = ['SERVER_JARFILE' => 'paper.jar', 'MAX_PLAYERS' => 10];

        $env = (new EnvironmentResolver())->resolve($configuration, $ports, $eggDefaults);

        $this->assertSame(25565, $env['GAME_PORT']);
        $this->assertSame('paper.jar', $env['SERVER_JARFILE']);
        $this->assertSame(10, $env['MAX_PLAYERS']);
    }

    public function test_mapped_variable_overrides_egg_default(): void
    {
        $configuration = $this->configurationWithMapping([
            ['variable_name' => 'MAX_PLAYERS', 'type' => 'static', 'static_value' => '50'],
        ]);

        $env = (new EnvironmentResolver())->resolve($configuration, [], ['MAX_PLAYERS' => 10]);

        $this->assertSame('50', $env['MAX_PLAYERS']);
    }

    public function test_handles_null_mapping_gracefully(): void
    {
        $configuration = new ServerConfiguration();
        $configuration->env_var_mapping = null;

        $env = (new EnvironmentResolver())->resolve($configuration, [], ['DEFAULT_KEY' => 'value']);

        $this->assertSame(['DEFAULT_KEY' => 'value'], $env);
    }

    public function test_skips_mapping_with_invalid_offset(): void
    {
        $configuration = $this->configurationWithMapping([
            ['variable_name' => 'OUT_OF_RANGE', 'type' => 'offset', 'offset_value' => 99],
        ]);

        $ports = [$this->alloc(25565)];

        $env = (new EnvironmentResolver())->resolve($configuration, $ports, []);

        // Out-of-range offset returns null → key skipped, env stays empty
        $this->assertArrayNotHasKey('OUT_OF_RANGE', $env);
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapping
     */
    private function configurationWithMapping(array $mapping): ServerConfiguration
    {
        $configuration = new ServerConfiguration();
        $configuration->env_var_mapping = $mapping;
        return $configuration;
    }

    private function alloc(int $port): PelicanAllocation
    {
        return new PelicanAllocation(
            id: $port, ip: '1.1.1.1', ipAlias: null, port: $port, notes: null, assigned: false,
        );
    }
}
