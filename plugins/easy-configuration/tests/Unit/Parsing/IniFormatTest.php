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

    public function test_it_appends_a_brand_new_section(): void
    {
        $result = (new IniFormat)->apply($this->sample(), [
            new ConfigChange('Foo', 'Bar', 'CustomSection'),
        ]);

        self::assertStringContainsString('[CustomSection]', $result);
        self::assertSame('Bar', (new IniFormat)->parse($result)->get('Foo', 'CustomSection')?->value);
    }
}
