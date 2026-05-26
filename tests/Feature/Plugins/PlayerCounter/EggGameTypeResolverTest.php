<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\PlayerCounter;

use App\Models\Egg;
use Plugins\PeregrinePlayerCounter\Services\EggGameTypeResolver;
use Tests\TestCase;

/**
 * Covers the egg→GameDig mapping. The counter officially supports six games
 * only (Minecraft, Valheim, 7 Days to Die, ARK ASA/ASE, Palworld); every other
 * egg resolves to "unsupported". The generic Steam fallback is opt-in (off by
 * default). The resolver is pure, so eggs are built in memory.
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

    public function test_both_ark_games_are_supported(): void
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

    public function test_removed_games_are_unsupported(): void
    {
        // CS2, Rust, Hytale, Sons of the Forest, Squad, … are no longer mapped.
        foreach (['Counter-Strike 2', 'Rust', 'Hytale', 'Sons of the Forest', 'Squad'] as $name) {
            $r = $this->resolve(['name' => $name]);
            $this->assertNull($r['type'], "$name should be unsupported");
            $this->assertFalse($r['queryable'], "$name should not be queryable");
        }
    }

    public function test_steam_fallback_is_off_by_default(): void
    {
        // An unknown SteamCMD egg is NOT auto-detected unless the opt-in fallback is on.
        $r = $this->resolve([
            'name' => 'Some Random Steam Game',
            'docker_image' => 'ghcr.io/pelican-eggs/steamcmd:debian',
            'startup' => 'steamcmd +app_update 123 +quit && ./srcds_run',
        ]);

        $this->assertNull($r['type']);
        $this->assertFalse($r['queryable']);
    }

    public function test_steam_fallback_can_be_enabled(): void
    {
        config()->set('peregrine-player-counter.steam_fallback.enabled', true);

        $r = $this->resolve([
            'name' => 'Some Random Steam Game',
            'docker_image' => 'ghcr.io/pelican-eggs/steamcmd:debian',
            'startup' => './srcds_run',
        ]);

        $this->assertSame('protocol-valve', $r['type']);
        $this->assertSame('source', $r['family']);
    }

    public function test_supported_game_wins_over_steam_fallback_when_enabled(): void
    {
        config()->set('peregrine-player-counter.steam_fallback.enabled', true);

        // ARK installs via SteamCMD, but the explicit rule must win over the
        // heuristic (which only runs after the rules).
        $r = $this->resolve([
            'name' => 'ARK Survival Ascended',
            'docker_image' => 'steamcmd/steamcmd',
            'startup' => 'steamcmd +app_update 2430930',
        ]);

        $this->assertSame('asa', $r['type']);
        $this->assertSame('eos', $r['family']);
    }

    public function test_null_egg_is_unsupported(): void
    {
        $r = (new EggGameTypeResolver)->resolve(null);

        $this->assertNull($r['type']);
        $this->assertSame('unknown', $r['family']);
        $this->assertFalse($r['queryable']);
    }
}
