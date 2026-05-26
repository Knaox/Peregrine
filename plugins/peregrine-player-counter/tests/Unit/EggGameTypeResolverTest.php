<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Tests\Unit;

use App\Models\Egg;
use PHPUnit\Framework\Attributes\Test;
use Plugins\PeregrinePlayerCounter\Services\EggGameTypeResolver;
use Plugins\PeregrinePlayerCounter\Tests\TestCase;

class EggGameTypeResolverTest extends TestCase
{
    private EggGameTypeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new EggGameTypeResolver;
    }

    private function egg(string $name, string $image = '', array $tags = []): Egg
    {
        return new Egg(['name' => $name, 'docker_image' => $image, 'tags' => $tags]);
    }

    #[Test]
    public function it_resolves_a_catalogue_game_with_its_offset_strategy(): void
    {
        $target = $this->resolver->resolve($this->egg('Valheim Dedicated Server'));

        $this->assertSame('valheim', $target['type']);
        $this->assertTrue($target['queryable']);
        $this->assertSame('offset', $target['query_port']['mode']);
        $this->assertSame(1, $target['query_port']['value']);
    }

    #[Test]
    public function it_resolves_sons_of_the_forest_with_its_fixed_query_port(): void
    {
        $target = $this->resolver->resolve($this->egg('Sons Of The Forest'));

        $this->assertSame('sotf', $target['type']);
        $this->assertSame('fixed', $target['query_port']['mode']);
        $this->assertSame(27016, $target['query_port']['value']);
    }

    #[Test]
    public function curated_overrides_win_over_the_catalogue(): void
    {
        // ARK: Survival Ascended is RCON-counted (family eos) via the override,
        // not whatever the generated catalogue would pick.
        $target = $this->resolver->resolve($this->egg('ARK: Survival Ascended'));

        $this->assertSame('asa', $target['type']);
        $this->assertSame('eos', $target['family']);
    }

    #[Test]
    public function minecraft_override_matches_broad_aliases(): void
    {
        $target = $this->resolver->resolve($this->egg('Paper 1.21'));

        $this->assertSame('minecraft', $target['type']);
        $this->assertSame('minecraft', $target['family']);
        $this->assertSame('same', $target['query_port']['mode']);
    }

    #[Test]
    public function unknown_eggs_fall_back_to_the_generic_a2s_probe(): void
    {
        $target = $this->resolver->resolve($this->egg('Totally Made Up Game 9000'));

        $this->assertSame('protocol-valve', $target['type']);
        $this->assertTrue($target['queryable']);
        $this->assertSame('same', $target['query_port']['mode']);
    }

    #[Test]
    public function a_null_egg_is_not_queryable(): void
    {
        $target = $this->resolver->resolve(null);

        $this->assertNull($target['type']);
        $this->assertFalse($target['queryable']);
    }

    #[Test]
    public function short_catalogue_keywords_do_not_cause_false_positives(): void
    {
        // Regression: "Astroneer" must NOT match Action: Source via the 2-char
        // legacy id "as" — short keywords (<4 chars) are dropped from the catalogue.
        $target = $this->resolver->resolve($this->egg('Astroneer Dedicated Server'));

        $this->assertNotSame('actionsource', $target['type']);
        $this->assertSame('protocol-valve', $target['type']); // generic fallback
    }

    #[Test]
    public function valheim_carries_console_count_patterns_for_the_crossplay_fallback(): void
    {
        $target = $this->resolver->resolve($this->egg('Valheim Dedicated Server'));

        $this->assertIsArray($target['console']);
        $this->assertArrayHasKey('count', $target['console']);
        $this->assertSame('(\\d+) player\\(s\\)', $target['console']['count']);
    }

    #[Test]
    public function games_without_a_console_rule_have_no_console_patterns(): void
    {
        $this->assertNull($this->resolver->resolve($this->egg('Rust'))['console']);
    }
}
