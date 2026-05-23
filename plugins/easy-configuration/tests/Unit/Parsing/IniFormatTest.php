<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Parsing\IniFormat;
use Plugins\EasyConfiguration\Support\ConfigChange;

final class IniFormatTest extends TestCase
{
    private function sample(): string
    {
        return implode("\n", [
            '; ARK GameUserSettings',
            '[ServerSettings]',
            'DifficultyOffset=0.200000',
            'ServerPVE=False',
            '',
            '[/script/shootergame.shootergamemode]',
            'bUseCorpseLocator=True',
            '',
        ]);
    }

    public function test_it_parses_keys_within_their_section(): void
    {
        $parsed = (new IniFormat)->parse($this->sample());

        self::assertSame('0.200000', $parsed->get('DifficultyOffset', 'ServerSettings')?->value);
        self::assertSame('False', $parsed->get('ServerPVE', 'ServerSettings')?->value);
        self::assertSame('True', $parsed->get('bUseCorpseLocator', '/script/shootergame.shootergamemode')?->value);
    }

    public function test_apply_with_no_changes_is_byte_identical(): void
    {
        $sample = $this->sample();

        self::assertSame($sample, (new IniFormat)->apply($sample, []));
    }

    public function test_it_changes_only_the_targeted_value(): void
    {
        $sample = $this->sample();

        $result = (new IniFormat)->apply($sample, [
            new ConfigChange('DifficultyOffset', '0.500000', 'ServerSettings'),
        ]);

        self::assertSame(str_replace('DifficultyOffset=0.200000', 'DifficultyOffset=0.500000', $sample), $result);
    }

    public function test_it_inserts_a_missing_key_into_its_existing_section(): void
    {
        $result = (new IniFormat)->apply($this->sample(), [
            new ConfigChange('MaxPlayers', '70', 'ServerSettings'),
        ]);

        $reparsed = (new IniFormat)->parse($result);
        self::assertSame('70', $reparsed->get('MaxPlayers', 'ServerSettings')?->value);
        // The new key lands in ServerSettings, not the Unreal section below it.
        self::assertNull($reparsed->get('MaxPlayers', '/script/shootergame.shootergamemode'));
    }

    public function test_it_appends_into_a_last_section_without_a_trailing_newline(): void
    {
        // No trailing newline on the final line: the appended key must start on
        // its own line, not glue onto it (regression: "ServerPVE=FalseMaxPlayers=70").
        $raw = "[ServerSettings]\nDifficultyOffset=0.2\nServerPVE=False";

        $result = (new IniFormat)->apply($raw, [
            new ConfigChange('MaxPlayers', '70', 'ServerSettings'),
        ]);

        self::assertStringNotContainsString('FalseMaxPlayers', $result);
        self::assertStringContainsString("ServerPVE=False\nMaxPlayers=70", $result);
        self::assertSame('70', (new IniFormat)->parse($result)->get('MaxPlayers', 'ServerSettings')?->value);
        self::assertSame('False', (new IniFormat)->parse($result)->get('ServerPVE', 'ServerSettings')?->value);
    }

    public function test_it_appends_a_brand_new_section(): void
    {
        $result = (new IniFormat)->apply($this->sample(), [
            new ConfigChange('Foo', 'Bar', 'CustomSection'),
        ]);

        self::assertStringContainsString('[CustomSection]', $result);
        self::assertSame('Bar', (new IniFormat)->parse($result)->get('Foo', 'CustomSection')?->value);
    }

    public function test_it_indexes_repeated_keys_with_their_occurrence(): void
    {
        $raw = implode("\n", [
            '[/script/shootergame.shootergamemode]',
            'ConfigOverrideItemMaxQuantity=A',
            'ConfigOverrideItemMaxQuantity=B',
            'ConfigOverrideItemMaxQuantity=C',
            '',
        ]);

        $repeats = array_values(array_filter(
            (new IniFormat)->parse($raw)->parameters,
            static fn ($p): bool => $p->key === 'ConfigOverrideItemMaxQuantity',
        ));

        self::assertCount(3, $repeats);
        self::assertSame([0, 1, 2], array_map(static fn ($p): int => $p->occurrence, $repeats));
        self::assertSame(['A', 'B', 'C'], array_map(static fn ($p): string => $p->value, $repeats));
    }

    public function test_apply_updates_only_the_targeted_occurrence(): void
    {
        $raw = implode("\n", ['[Section]', 'Key=A', 'Key=B', 'Key=C', '']);

        $result = (new IniFormat)->apply($raw, [
            new ConfigChange('Key', 'B2', 'Section', 1),
        ]);

        $values = array_map(
            static fn ($p): string => $p->value,
            array_values(array_filter((new IniFormat)->parse($result)->parameters, static fn ($p): bool => $p->key === 'Key')),
        );

        self::assertSame(['A', 'B2', 'C'], $values);
    }

    public function test_apply_appends_a_new_occurrence_beyond_the_existing_ones(): void
    {
        // The "add a repeatable key" flow targets occurrence = count of existing
        // copies, so the writer APPENDS a new line instead of overwriting one.
        $raw = implode("\n", ['[Section]', 'Key=A', 'Key=B', 'Key=C', '']);

        $result = (new IniFormat)->apply($raw, [
            new ConfigChange('Key', 'D', 'Section', 3),
        ]);

        $values = array_map(
            static fn ($p): string => $p->value,
            array_values(array_filter((new IniFormat)->parse($result)->parameters, static fn ($p): bool => $p->key === 'Key')),
        );

        self::assertSame(['A', 'B', 'C', 'D'], $values);
    }
}
