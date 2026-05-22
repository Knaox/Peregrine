<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Config\ConfigMerger;
use Plugins\EasyConfiguration\Services\Parsing\TypeDetector;
use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

final class ConfigMergerTest extends TestCase
{
    private function merger(): ConfigMerger
    {
        return new ConfigMerger(new TypeDetector);
    }

    public function test_it_orders_template_params_first_then_auto_detected_extras(): void
    {
        $fileDef = [
            'format' => 'properties',
            'parameters' => [
                'max-players' => ['display_type' => 'slider', 'config' => ['min' => 1, 'max' => 100], 'label' => ['en' => 'Max']],
                'pvp' => ['display_type' => 'boolean', 'label' => ['en' => 'PvP']],
            ],
        ];
        $parsed = new ParsedConfig([
            new ConfigParameter('max-players', '50'),
            new ConfigParameter('pvp', 'true'),
            new ConfigParameter('level-name', 'world'),
        ]);

        $result = $this->merger()->merge($fileDef, $parsed);

        self::assertFalse($result['sectioned']);
        self::assertCount(3, $result['parameters']);

        self::assertSame('max-players', $result['parameters'][0]['key']);
        self::assertSame('50', $result['parameters'][0]['value']);
        self::assertSame('slider', $result['parameters'][0]['display_type']);
        self::assertFalse($result['parameters'][0]['inferred']);

        $extra = $result['parameters'][2];
        self::assertSame('level-name', $extra['key']);
        self::assertSame('text', $extra['display_type']);
        self::assertTrue($extra['inferred']);
    }

    public function test_it_falls_back_to_the_template_default_when_the_key_is_absent(): void
    {
        $fileDef = [
            'format' => 'properties',
            'parameters' => [
                'difficulty' => ['display_type' => 'select', 'config' => ['default' => 'normal']],
            ],
        ];

        $result = $this->merger()->merge($fileDef, new ParsedConfig([]));

        self::assertSame('normal', $result['parameters'][0]['value']);
    }

    public function test_it_filters_sections_outside_the_whitelist(): void
    {
        $fileDef = [
            'format' => 'ini',
            'section_whitelist' => ['ServerSettings'],
            'parameters' => [
                'ServerSettings' => ['MaxPlayers' => ['display_type' => 'number', 'label' => ['en' => 'Max']]],
                'OtherSection' => ['Foo' => ['display_type' => 'text']],
            ],
        ];
        $parsed = new ParsedConfig([
            new ConfigParameter('MaxPlayers', '70', 'ServerSettings'),
            new ConfigParameter('Foo', 'bar', 'OtherSection'),
            new ConfigParameter('Extra', 'x', 'ServerSettings'),
        ]);

        $result = $this->merger()->merge($fileDef, $parsed);

        self::assertTrue($result['sectioned']);
        $sections = array_column($result['parameters'], 'section');
        self::assertNotContains('OtherSection', $sections);

        $keys = array_column($result['parameters'], 'key');
        self::assertContains('MaxPlayers', $keys);
        self::assertContains('Extra', $keys);
    }
}
