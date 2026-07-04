<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Parsing\XmlPropertyFormat;
use Plugins\EasyConfiguration\Support\ConfigChange;

final class XmlPropertyFormatTest extends TestCase
{
    private function sample(): string
    {
        return implode("\n", [
            '<?xml version="1.0"?>',
            '<ServerSettings>',
            '	<!-- General -->',
            '	<property name="ServerName" value="My Game Host" />',
            '	<property name="ServerDescription" value="A 7 Days to Die server" />',
            '	<property name="ServerMaxPlayerCount" value="8" />',
            '	<property name="ServerPassword" value="" />',
            '</ServerSettings>',
            '',
        ]);
    }

    public function test_each_property_is_one_parameter_keyed_by_its_name(): void
    {
        $parsed = (new XmlPropertyFormat)->parse($this->sample());

        self::assertSame('My Game Host', $parsed->get('ServerName', 'ServerSettings')?->value);
        self::assertSame('A 7 Days to Die server', $parsed->get('ServerDescription', 'ServerSettings')?->value);
        self::assertSame('8', $parsed->get('ServerMaxPlayerCount', 'ServerSettings')?->value);
        self::assertSame('', $parsed->get('ServerPassword', 'ServerSettings')?->value);
    }

    public function test_it_does_not_expose_the_name_or_value_attributes_as_keys(): void
    {
        $parsed = (new XmlPropertyFormat)->parse($this->sample());

        // The generic-xml footgun (property@name / property@value) must not appear.
        self::assertNull($parsed->get('ServerSettings.property@name'));
        self::assertNull($parsed->get('ServerSettings.property@value'));
    }

    public function test_apply_with_no_changes_is_byte_identical(): void
    {
        $sample = $this->sample();

        self::assertSame($sample, (new XmlPropertyFormat)->apply($sample, []));
    }

    public function test_it_rewrites_only_the_value_attribute_keeping_name_and_layout(): void
    {
        $result = (new XmlPropertyFormat)->apply($this->sample(), [
            new ConfigChange('ServerMaxPlayerCount', '16', 'ServerSettings'),
        ]);

        self::assertStringContainsString('<property name="ServerMaxPlayerCount" value="16" />', $result);
        self::assertStringContainsString('<!-- General -->', $result);
        self::assertSame('16', (new XmlPropertyFormat)->parse($result)->get('ServerMaxPlayerCount', 'ServerSettings')?->value);
    }

    public function test_it_fills_an_empty_value(): void
    {
        $result = (new XmlPropertyFormat)->apply($this->sample(), [
            new ConfigChange('ServerPassword', 'secret', 'ServerSettings'),
        ]);

        self::assertStringContainsString('name="ServerPassword" value="secret"', $result);
    }

    public function test_it_escapes_special_characters_in_the_value(): void
    {
        $result = (new XmlPropertyFormat)->apply($this->sample(), [
            new ConfigChange('ServerName', 'Bob & "Friends"', 'ServerSettings'),
        ]);

        self::assertStringContainsString('value="Bob &amp; &quot;Friends&quot;"', $result);
        self::assertSame('Bob & "Friends"', (new XmlPropertyFormat)->parse($result)->get('ServerName', 'ServerSettings')?->value);
    }

    public function test_a_missing_key_is_appended_to_its_section(): void
    {
        $result = (new XmlPropertyFormat)->apply($this->sample(), [
            new ConfigChange('SandboxCode', 'AAAJABJACJADJARFBNC', 'ServerSettings'),
        ]);

        // Appended on its own line after the section's last property, with the
        // same indentation, and round-trips through the parser.
        self::assertStringContainsString("\n\t<property name=\"SandboxCode\" value=\"AAAJABJACJADJARFBNC\"/>\n", $result);
        self::assertSame('AAAJABJACJADJARFBNC', (new XmlPropertyFormat)->parse($result)->get('SandboxCode', 'ServerSettings')?->value);
        self::assertStringContainsString('<property name="ServerPassword" value="" />', $result);
        self::assertLessThan(
            strpos($result, '<property name="SandboxCode"'),
            strpos($result, '<property name="ServerPassword"'),
        );
    }

    public function test_missing_keys_append_in_submitted_order_alongside_rewrites(): void
    {
        $result = (new XmlPropertyFormat)->apply($this->sample(), [
            new ConfigChange('ServerMaxPlayerCount', '16', 'ServerSettings'),
            new ConfigChange('SandboxCode', 'AAA', 'ServerSettings'),
            new ConfigChange('TelnetEnabled', 'true', 'ServerSettings'),
        ]);

        $parsed = (new XmlPropertyFormat)->parse($result);
        self::assertSame('16', $parsed->get('ServerMaxPlayerCount', 'ServerSettings')?->value);
        self::assertSame('AAA', $parsed->get('SandboxCode', 'ServerSettings')?->value);
        self::assertSame('true', $parsed->get('TelnetEnabled', 'ServerSettings')?->value);
        self::assertLessThan(
            strpos($result, '<property name="TelnetEnabled"'),
            strpos($result, '<property name="SandboxCode"'),
        );
    }

    public function test_a_missing_key_is_appended_into_an_empty_section(): void
    {
        $raw = "<ServerSettings>\n</ServerSettings>\n";

        $result = (new XmlPropertyFormat)->apply($raw, [new ConfigChange('ServerName', 'Hi', 'ServerSettings')]);

        self::assertSame("<ServerSettings>\n\t<property name=\"ServerName\" value=\"Hi\"/>\n</ServerSettings>\n", $result);
    }

    public function test_a_missing_key_value_is_escaped_on_insertion(): void
    {
        $result = (new XmlPropertyFormat)->apply($this->sample(), [
            new ConfigChange('Motd', 'Bob & "Friends"', 'ServerSettings'),
        ]);

        self::assertStringContainsString('value="Bob &amp; &quot;Friends&quot;"', $result);
        self::assertSame('Bob & "Friends"', (new XmlPropertyFormat)->parse($result)->get('Motd', 'ServerSettings')?->value);
    }

    public function test_a_change_for_an_unknown_section_is_skipped_losslessly(): void
    {
        $sample = $this->sample();

        $result = (new XmlPropertyFormat)->apply($sample, [new ConfigChange('Nope', 'x', 'Nowhere')]);

        self::assertSame($sample, $result);
    }

    public function test_it_handles_single_quoted_values(): void
    {
        $raw = "<ServerSettings>\n<property name='Region' value='NorthAmericaEast' />\n</ServerSettings>\n";

        $result = (new XmlPropertyFormat)->apply($raw, [
            new ConfigChange('Region', 'Europe', 'ServerSettings'),
        ]);

        self::assertStringContainsString("name='Region' value='Europe'", $result);
    }
}
