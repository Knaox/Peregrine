<?php

namespace Tests\Unit\Bridge;

use App\Models\ServerPlan;
use App\Services\Bridge\EnvironmentResolver;
use App\Services\Pelican\DTOs\PelicanAllocation;
use Tests\TestCase;

class EnvironmentResolverTest extends TestCase
{
    public function test_resolves_offset_type_correctly(): void
    {
        $plan = $this->planWithMapping([
            ['variable_name' => 'GAME_PORT', 'type' => 'offset', 'offset_value' => 0],
            ['variable_name' => 'TELNET_PORT', 'type' => 'offset', 'offset_value' => 1],
        ]);

        $ports = [
            $this->alloc(25565),
            $this->alloc(25566),
        ];

        $env = (new EnvironmentResolver())->resolve($plan, $ports, []);

        $this->assertSame(25565, $env['GAME_PORT']);
        $this->assertSame(25566, $env['TELNET_PORT']);
    }

    public function test_resolves_static_type_passthrough(): void
    {
        $plan = $this->planWithMapping([
            ['variable_name' => 'MAX_PLAYERS', 'type' => 'static', 'static_value' => '20'],
        ]);

        $env = (new EnvironmentResolver())->resolve($plan, [], []);

        $this->assertSame('20', $env['MAX_PLAYERS']);
    }

    public function test_resolves_random_type_picks_one_of_allocated(): void
    {
        $plan = $this->planWithMapping([
            ['variable_name' => 'QUERY_PORT', 'type' => 'random'],
        ]);

        $ports = [$this->alloc(25565), $this->alloc(25566), $this->alloc(25567)];

        $env = (new EnvironmentResolver())->resolve($plan, $ports, []);

        $this->assertContains($env['QUERY_PORT'], [25565, 25566, 25567]);
    }

    public function test_fills_defaults_for_unmapped_egg_variables(): void
    {
        $plan = $this->planWithMapping([
            ['variable_name' => 'GAME_PORT', 'type' => 'offset', 'offset_value' => 0],
        ]);

        $ports = [$this->alloc(25565)];
        $eggDefaults = ['SERVER_JARFILE' => 'paper.jar', 'MAX_PLAYERS' => 10];

        $env = (new EnvironmentResolver())->resolve($plan, $ports, $eggDefaults);

        $this->assertSame(25565, $env['GAME_PORT']);
        $this->assertSame('paper.jar', $env['SERVER_JARFILE']);
        $this->assertSame(10, $env['MAX_PLAYERS']);
    }

    public function test_mapped_variable_overrides_egg_default(): void
    {
        $plan = $this->planWithMapping([
            ['variable_name' => 'MAX_PLAYERS', 'type' => 'static', 'static_value' => '50'],
        ]);

        $env = (new EnvironmentResolver())->resolve($plan, [], ['MAX_PLAYERS' => 10]);

        $this->assertSame('50', $env['MAX_PLAYERS']);
    }

    public function test_handles_null_mapping_gracefully(): void
    {
        $plan = new ServerPlan();
        $plan->env_var_mapping = null;

        $env = (new EnvironmentResolver())->resolve($plan, [], ['DEFAULT_KEY' => 'value']);

        $this->assertSame(['DEFAULT_KEY' => 'value'], $env);
    }

    public function test_skips_mapping_with_invalid_offset(): void
    {
        $plan = $this->planWithMapping([
            ['variable_name' => 'OUT_OF_RANGE', 'type' => 'offset', 'offset_value' => 99],
        ]);

        $ports = [$this->alloc(25565)];

        $env = (new EnvironmentResolver())->resolve($plan, $ports, []);

        // Out-of-range offset returns null → key skipped, env stays empty
        $this->assertArrayNotHasKey('OUT_OF_RANGE', $env);
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapping
     */
    private function planWithMapping(array $mapping): ServerPlan
    {
        $plan = new ServerPlan();
        $plan->env_var_mapping = $mapping;
        return $plan;
    }

    private function alloc(int $port): PelicanAllocation
    {
        return new PelicanAllocation(
            id: $port, ip: '1.1.1.1', ipAlias: null, port: $port, notes: null, assigned: false,
        );
    }
}
