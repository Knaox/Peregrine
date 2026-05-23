<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Parsing\PalworldFormat;
use Plugins\EasyConfiguration\Support\ConfigChange;

final class PalworldFormatTest extends TestCase
{
    private const SECTION = '/Script/Pal.PalGameWorldSettings';

    private function sample(): string
    {
        return implode("\n", [
            '[/Script/Pal.PalGameWorldSettings]',
            'OptionSettings=(Difficulty=None,DayTimeSpeedRate=1.000000,ServerName="My, server",bIsPvP=False)',
            '',
        ]);
    }

    public function test_it_expands_each_option_into_its_own_parameter(): void
    {
        $parsed = (new PalworldFormat)->parse($this->sample());

        self::assertSame('None', $parsed->get('Difficulty', self::SECTION)?->value);
        self::assertSame('1.000000', $parsed->get('DayTimeSpeedRate', self::SECTION)?->value);
        // Quotes are stripped for editing; the comma inside the quotes is kept.
        self::assertSame('My, server', $parsed->get('ServerName', self::SECTION)?->value);
        self::assertSame('False', $parsed->get('bIsPvP', self::SECTION)?->value);
        // The container key itself is not surfaced.
        self::assertNull($parsed->get('OptionSettings', self::SECTION));
    }

    public function test_apply_with_no_changes_is_byte_identical(): void
    {
        $sample = $this->sample();

        self::assertSame($sample, (new PalworldFormat)->apply($sample, []));
    }

    public function test_it_updates_one_inner_value_and_keeps_the_rest(): void
    {
        $format = new PalworldFormat;
        $result = $format->apply($this->sample(), [
            new ConfigChange('Difficulty', 'Hard', self::SECTION),
        ]);

        $reparsed = $format->parse($result);
        self::assertSame('Hard', $reparsed->get('Difficulty', self::SECTION)?->value);
        self::assertSame('1.000000', $reparsed->get('DayTimeSpeedRate', self::SECTION)?->value);
        self::assertSame('My, server', $reparsed->get('ServerName', self::SECTION)?->value);
    }

    public function test_it_preserves_quoting_for_a_changed_string_value(): void
    {
        $format = new PalworldFormat;
        $result = $format->apply($this->sample(), [
            new ConfigChange('ServerName', 'Brand New Name', self::SECTION),
        ]);

        self::assertStringContainsString('ServerName="Brand New Name"', $result);
        self::assertSame('Brand New Name', $format->parse($result)->get('ServerName', self::SECTION)?->value);
    }
}
