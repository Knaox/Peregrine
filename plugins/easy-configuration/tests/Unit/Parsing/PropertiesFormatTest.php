<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Parsing\PropertiesFormat;
use Plugins\EasyConfiguration\Support\ConfigChange;

final class PropertiesFormatTest extends TestCase
{
    private function sample(): string
    {
        return implode("\n", [
            '#Minecraft server properties',
            '#Sat May 22 18:00:00 UTC 2026',
            'max-players=20',
            'pvp=true',
            'motd=A Minecraft Server',
            'level-seed=',
            '',
        ]);
    }

    public function test_it_parses_keys_and_skips_comments(): void
    {
        $parsed = (new PropertiesFormat)->parse($this->sample());

        self::assertCount(4, $parsed->parameters);
        self::assertSame('20', $parsed->get('max-players')?->value);
        self::assertSame('true', $parsed->get('pvp')?->value);
        self::assertSame('', $parsed->get('level-seed')?->value);
    }

    public function test_apply_with_no_changes_is_byte_identical(): void
    {
        $sample = $this->sample();

        self::assertSame($sample, (new PropertiesFormat)->apply($sample, []));
    }

    public function test_it_changes_only_the_targeted_value(): void
    {
        $sample = $this->sample();

        $result = (new PropertiesFormat)->apply($sample, [new ConfigChange('max-players', '50')]);

        self::assertSame(str_replace('max-players=20', 'max-players=50', $sample), $result);
    }

    public function test_it_preserves_crlf_line_endings(): void
    {
        $crlf = str_replace("\n", "\r\n", $this->sample());

        $result = (new PropertiesFormat)->apply($crlf, [new ConfigChange('pvp', 'false')]);

        self::assertSame(str_replace('pvp=true', 'pvp=false', $crlf), $result);
        self::assertStringContainsString("\r\n", $result);
    }

    public function test_it_appends_a_missing_key(): void
    {
        $sample = $this->sample();

        $result = (new PropertiesFormat)->apply($sample, [new ConfigChange('view-distance', '10')]);

        self::assertSame($sample.'view-distance=10'."\n", $result);
    }

    public function test_repeated_keys_are_indexed_and_edited_per_occurrence(): void
    {
        // The occurrence mechanism is uniform across all line-based formats.
        $raw = implode("\n", ['rule=a', 'rule=b', 'rule=c', '']);

        $repeats = array_values(array_filter(
            (new PropertiesFormat)->parse($raw)->parameters,
            static fn ($p): bool => $p->key === 'rule',
        ));
        self::assertSame([0, 1, 2], array_map(static fn ($p): int => $p->occurrence, $repeats));

        $result = (new PropertiesFormat)->apply($raw, [new ConfigChange('rule', 'b2', null, 1)]);
        $values = array_map(
            static fn ($p): string => $p->value,
            array_values(array_filter((new PropertiesFormat)->parse($result)->parameters, static fn ($p): bool => $p->key === 'rule')),
        );
        self::assertSame(['a', 'b2', 'c'], $values);
    }
}
