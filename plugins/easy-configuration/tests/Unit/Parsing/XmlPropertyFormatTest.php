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

    public function test_an_unknown_key_is_skipped_losslessly(): void
    {
        $sample = $this->sample();

        $result = (new XmlPropertyFormat)->apply($sample, [new ConfigChange('Nope', 'x', 'ServerSettings')]);

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
