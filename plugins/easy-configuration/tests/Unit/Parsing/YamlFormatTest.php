<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Parsing\YamlFormat;
use Plugins\EasyConfiguration\Support\ConfigChange;

final class YamlFormatTest extends TestCase
{
    private function sample(): string
    {
        return implode("\n", [
            '# Paper global config',
            'settings:',
            '  allow-end: true',
            '  spawn-radius: 16',
            'world:',
            '  name: world  # the default world',
            '  motd: "Hello"',
            '',
        ]);
    }

    public function test_it_flattens_nested_mappings_to_dotted_paths(): void
    {
        $parsed = (new YamlFormat)->parse($this->sample());

        self::assertSame('true', $parsed->get('settings.allow-end')?->value);
        self::assertSame('16', $parsed->get('settings.spawn-radius')?->value);
        self::assertSame('world', $parsed->get('world.name')?->value);  // inline comment stripped
        self::assertSame('Hello', $parsed->get('world.motd')?->value);  // quotes stripped
    }

    public function test_apply_with_no_changes_is_byte_identical(): void
    {
        $sample = $this->sample();

        self::assertSame($sample, (new YamlFormat)->apply($sample, []));
    }

    public function test_it_changes_a_nested_scalar_preserving_indent(): void
    {
        $sample = $this->sample();

        $result = (new YamlFormat)->apply($sample, [new ConfigChange('settings.spawn-radius', '32')]);

        self::assertSame(str_replace('  spawn-radius: 16', '  spawn-radius: 32', $sample), $result);
    }

    public function test_it_preserves_an_inline_comment_on_a_changed_line(): void
    {
        $sample = $this->sample();

        $result = (new YamlFormat)->apply($sample, [new ConfigChange('world.name', 'nether')]);

        self::assertStringContainsString('  name: nether  # the default world', $result);
    }

    public function test_it_keeps_quotes_on_a_quoted_value(): void
    {
        $result = (new YamlFormat)->apply($this->sample(), [new ConfigChange('world.motd', 'Hi there')]);

        self::assertStringContainsString('  motd: "Hi there"', $result);
    }
}
