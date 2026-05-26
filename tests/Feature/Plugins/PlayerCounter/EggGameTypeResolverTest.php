<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\PlayerCounter;

use App\Models\Egg;
use Plugins\PeregrinePlayerCounter\Services\EggGameTypeResolver;
use Tests\TestCase;

/**
 * Covers the egg→GameDig mapping. Six games have dedicated rules (Minecraft,
 * Valheim, 7 Days to Die, ARK ASA/ASE, Palworld); every other egg falls back to
 * a generic A2S probe ('protocol-valve') so the card still shows and attempts a
 * count. The resolver is pure, so eggs are built in memory.
 */
class EggGameTypeResolverTest extends TestCase
{
    use ActivatesPlayerCounterPlugin;

    protected function setUp(): void
    {
        $this->bootPlayerCounterPlugin();
        parent::setUp();
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array{type: ?string, family: string, queryable: bool, query_offset: int}
     */
    private function resolve(array $attrs): array
    {
        return (new EggGameTypeResolver)->resolve(new Egg($attrs));
    }

    public function test_minecraft_java_and_bedrock(): void
    {
        $this->assertSame('minecraft', $this->resolve(['name' => 'Paper'])['type']);
        $this->assertSame('mbe', $this->resolve(['name' => 'Pocketmine Bedrock'])['type']);
    }

    public function test_valheim_and_seven_days_to_die(): void
    {
        $this->assertSame('valheim', $this->resolve(['name' => 'Valheim Dedicated'])['type']);
        $this->assertSame('sdtd', $this->resolve(['name' => '7 Days to Die'])['type']);
    }

    public function test_both_ark_games_use_their_dedicated_type(): void
    {
        $asa = $this->resolve(['name' => 'ARK: Survival Ascended']);
        $this->assertSame('asa', $asa['type']);
        $this->assertSame('eos', $asa['family']);

        $ase = $this->resolve(['name' => 'ARK: Survival Evolved']);
        $this->assertSame('ase', $ase['type']);
    }

    public function test_palworld_maps_to_rcon_palworld_type(): void
    {
        $r = $this->resolve(['name' => 'Palworld', 'docker_image' => 'ghcr.io/parkervcp/games:palworld']);

        $this->assertSame('palworld', $r['type']);
        $this->assertTrue($r['queryable']);
    }

    public function test_unmapped_games_fall_back_to_generic_a2s(): void
    {
        // No dedicated rule → generic A2S probe so the card still shows/queries.
        foreach (['Counter-Strike 2', 'Rust', 'Hytale', 'Sons of the Forest', 'Some Random Game'] as $name) {
            $r = $this->resolve(['name' => $name]);
            $this->assertSame('protocol-valve', $r['type'], "$name should fall back to protocol-valve");
            $this->assertSame('other', $r['family']);
            $this->assertTrue($r['queryable']);
        }
    }

    public function test_dedicated_rule_wins_over_fallback(): void
    {
        // A supported game keeps its precise type, never the generic fallback.
        $this->assertSame('valheim', $this->resolve(['name' => 'Valheim'])['type']);
        $this->assertSame('asa', $this->resolve(['name' => 'ARK Survival Ascended'])['type']);
    }

    public function test_fallback_type_can_be_disabled(): void
    {
        config()->set('peregrine-player-counter.fallback_type', '');

        $r = $this->resolve(['name' => 'Counter-Strike 2']);

        $this->assertNull($r['type']);
        $this->assertFalse($r['queryable']);
    }

    public function test_egg_without_type_is_unqueryable(): void
    {
        $r = (new EggGameTypeResolver)->resolve(null);

        $this->assertNull($r['type']);
        $this->assertFalse($r['queryable']);
    }
}
