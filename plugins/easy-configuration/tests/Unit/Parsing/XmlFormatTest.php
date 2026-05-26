<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Parsing\XmlFormat;
use Plugins\EasyConfiguration\Support\ConfigChange;

final class XmlFormatTest extends TestCase
{
    private function sample(): string
    {
        return implode("\n", [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<!-- server config -->',
            '<config>',
            '    <server>',
            '        <name>My Server</name>',
            '        <port>25565</port>',
            '    </server>',
            '    <slot index="0" enabled="true" />',
            '</config>',
            '',
        ]);
    }

    public function test_it_parses_text_and_attributes_as_flat_dotted_keys(): void
    {
        $parsed = (new XmlFormat)->parse($this->sample());

        self::assertSame('My Server', $parsed->get('config.server.name')?->value);
        self::assertSame('25565', $parsed->get('config.server.port')?->value);
        self::assertSame('0', $parsed->get('config.slot@index')?->value);
        self::assertSame('true', $parsed->get('config.slot@enabled')?->value);
    }

    public function test_apply_with_no_changes_is_byte_identical(): void
    {
        $sample = $this->sample();

        self::assertSame($sample, (new XmlFormat)->apply($sample, []));
    }

    public function test_it_changes_element_text_preserving_indentation_and_comments(): void
    {
        $result = (new XmlFormat)->apply($this->sample(), [
            new ConfigChange('config.server.port', '30000'),
        ]);

        self::assertStringContainsString('        <port>30000</port>', $result);
        self::assertStringContainsString('<!-- server config -->', $result);
        self::assertSame('30000', (new XmlFormat)->parse($result)->get('config.server.port')?->value);
    }

    public function test_it_changes_an_attribute_keeping_its_quote_style(): void
    {
        $result = (new XmlFormat)->apply($this->sample(), [
            new ConfigChange('config.slot@enabled', 'false'),
        ]);

        self::assertStringContainsString('enabled="false"', $result);
    }

    public function test_it_escapes_xml_special_characters(): void
    {
        $result = (new XmlFormat)->apply($this->sample(), [
            new ConfigChange('config.server.name', 'A & B <test>'),
        ]);

        self::assertStringContainsString('<name>A &amp; B &lt;test&gt;</name>', $result);
        self::assertSame('A & B <test>', (new XmlFormat)->parse($result)->get('config.server.name')?->value);
    }

    public function test_it_handles_repeated_elements_by_occurrence(): void
    {
        $raw = "<list>\n  <item>a</item>\n  <item>b</item>\n</list>\n";

        $result = (new XmlFormat)->apply($raw, [
            new ConfigChange('list.item', 'B', null, 1),
        ]);

        self::assertSame("<list>\n  <item>a</item>\n  <item>B</item>\n</list>\n", $result);
    }

    public function test_it_decodes_entities_on_read(): void
    {
        $parsed = (new XmlFormat)->parse('<a>1 &amp; 2 &lt; 3</a>');

        self::assertSame('1 & 2 < 3', $parsed->get('a')?->value);
    }

    public function test_an_unknown_key_is_skipped_losslessly(): void
    {
        $sample = $this->sample();

        $result = (new XmlFormat)->apply($sample, [new ConfigChange('config.does.not.exist', 'x')]);

        self::assertSame($sample, $result);
    }

    public function test_it_preserves_crlf_line_endings(): void
    {
        $raw = "<config>\r\n  <port>1</port>\r\n</config>\r\n";

        $result = (new XmlFormat)->apply($raw, [new ConfigChange('config.port', '2')]);

        self::assertSame("<config>\r\n  <port>2</port>\r\n</config>\r\n", $result);
    }
}
