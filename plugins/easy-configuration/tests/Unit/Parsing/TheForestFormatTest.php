<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Parsing\TheForestFormat;
use Plugins\EasyConfiguration\Support\ConfigChange;

final class TheForestFormatTest extends TestCase
{
    private function sample(): string
    {
        return implode("\n", [
            '//The Forest dedicated server',
            'serverName "My Server"',
            'serverPlayers 8',
            'difficulty "Normal"',
            'veganMode off',
            '',
        ]);
    }

    public function test_it_parses_key_value_pairs_unquoting_strings(): void
    {
        $parsed = (new TheForestFormat)->parse($this->sample());

        self::assertSame('My Server', $parsed->get('serverName')?->value);
        self::assertSame('8', $parsed->get('serverPlayers')?->value);
        self::assertSame('Normal', $parsed->get('difficulty')?->value);
        self::assertSame('off', $parsed->get('veganMode')?->value);
    }

    public function test_apply_with_no_changes_is_byte_identical(): void
    {
        $sample = $this->sample();

        self::assertSame($sample, (new TheForestFormat)->apply($sample, []));
    }

    public function test_it_changes_a_value_and_preserves_comments(): void
    {
        $format = new TheForestFormat;
        $result = $format->apply($this->sample(), [
            new ConfigChange('serverPlayers', '16'),
        ]);

        self::assertStringContainsString('//The Forest dedicated server', $result);
        self::assertSame('16', $format->parse($result)->get('serverPlayers')?->value);
    }

    public function test_it_requotes_a_changed_string_value(): void
    {
        $format = new TheForestFormat;
        $result = $format->apply($this->sample(), [
            new ConfigChange('serverName', 'A New Name'),
        ]);

        self::assertStringContainsString('serverName "A New Name"', $result);
        self::assertSame('A New Name', $format->parse($result)->get('serverName')?->value);
    }

    public function test_it_appends_a_missing_key(): void
    {
        $format = new TheForestFormat;
        $result = $format->apply($this->sample(), [
            new ConfigChange('serverPassword', 'secret'),
        ]);

        self::assertSame('secret', $format->parse($result)->get('serverPassword')?->value);
    }
}
