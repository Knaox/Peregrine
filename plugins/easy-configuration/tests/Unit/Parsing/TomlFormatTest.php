<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Parsing\TomlFormat;
use Plugins\EasyConfiguration\Support\ConfigChange;

final class TomlFormatTest extends TestCase
{
    private function sample(): string
    {
        return implode("\n", [
            '# Forge mod config',
            '[general]',
            'enableMod = true',
            'maxEntities = 100  # hard cap',
            'name = "server"',
            '',
        ]);
    }

    public function test_it_parses_scalars_within_tables(): void
    {
        $parsed = (new TomlFormat)->parse($this->sample());

        self::assertSame('true', $parsed->get('enableMod', 'general')?->value);
        self::assertSame('100', $parsed->get('maxEntities', 'general')?->value);
        self::assertSame('server', $parsed->get('name', 'general')?->value);  // unquoted
    }

    public function test_apply_with_no_changes_is_byte_identical(): void
    {
        $sample = $this->sample();

        self::assertSame($sample, (new TomlFormat)->apply($sample, []));
    }

    public function test_it_changes_a_value_preserving_inline_comment(): void
    {
        $result = (new TomlFormat)->apply($this->sample(), [
            new ConfigChange('maxEntities', '250', 'general'),
        ]);

        self::assertStringContainsString('maxEntities = 250  # hard cap', $result);
    }

    public function test_it_keeps_string_quoting(): void
    {
        $result = (new TomlFormat)->apply($this->sample(), [
            new ConfigChange('name', 'prod', 'general'),
        ]);

        self::assertStringContainsString('name = "prod"', $result);
    }

    public function test_it_changes_a_boolean(): void
    {
        $sample = $this->sample();

        $result = (new TomlFormat)->apply($sample, [new ConfigChange('enableMod', 'false', 'general')]);

        self::assertSame(str_replace('enableMod = true', 'enableMod = false', $sample), $result);
    }

    public function test_it_appends_into_a_last_table_without_a_trailing_newline(): void
    {
        // No trailing newline on the final line: the appended key must start on
        // its own line, not glue onto it.
        $raw = "[general]\nenableMod = true\nname = \"server\"";

        $result = (new TomlFormat)->apply($raw, [
            new ConfigChange('maxEntities', '100', 'general'),
        ]);

        self::assertStringNotContainsString('"server"maxEntities', $result);
        self::assertSame('100', (new TomlFormat)->parse($result)->get('maxEntities', 'general')?->value);
        self::assertSame('server', (new TomlFormat)->parse($result)->get('name', 'general')?->value);
    }
}
