<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Import;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Import\ConfigImportScaffolder;
use Plugins\EasyConfiguration\Services\Parsing\TypeDetector;
use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

final class ConfigImportScaffolderTest extends TestCase
{
    private function scaffolder(): ConfigImportScaffolder
    {
        return new ConfigImportScaffolder(new TypeDetector);
    }

    #[DataProvider('formatCases')]
    public function test_it_detects_format_from_extension(string $path, ?string $expected): void
    {
        self::assertSame($expected, ConfigImportScaffolder::detectFormat($path));
    }

    /** @return list<array{string, ?string}> */
    public static function formatCases(): array
    {
        return [
            ['server.properties', 'properties'],
            ['GameUserSettings.ini', 'ini'],
            ['config/bukkit.yml', 'yaml'],
            ['paper.yaml', 'yaml'],
            ['config.json', 'json'],
            ['settings.toml', 'toml'],
            ['custom.cfg', 'ini'],
            ['daemon.conf', 'ini'],
            ['notes.txt', null],
            ['no-extension', null],
        ];
    }

    public function test_it_scaffolds_flat_parameters_with_guessed_types_and_defaults(): void
    {
        $parsed = new ParsedConfig([
            new ConfigParameter('difficulty', 'hard'),
            new ConfigParameter('pvp', 'true'),
            new ConfigParameter('max-players', '20'),
        ]);

        $file = $this->scaffolder()->scaffold('server.properties', 'properties', $parsed);

        self::assertSame('server-properties', $file['id']);
        self::assertSame('server.properties', $file['path']);
        self::assertSame('properties', $file['format']);
        self::assertTrue($file['enabled']);

        $params = $file['parameters'];
        self::assertSame('text', $params['difficulty']['display_type']);
        self::assertSame('hard', $params['difficulty']['config']['default']);

        self::assertSame('boolean', $params['pvp']['display_type']);
        self::assertSame('true', $params['pvp']['config']['true_value']);
        self::assertSame('false', $params['pvp']['config']['false_value']);

        self::assertSame('number', $params['max-players']['display_type']);
        self::assertSame('20', $params['max-players']['config']['default']);
    }

    public function test_it_nests_sectioned_parameters(): void
    {
        $parsed = new ParsedConfig([
            new ConfigParameter('Name', 'Test', 'ServerSettings'),
            new ConfigParameter('Port', '7777', 'ServerSettings'),
            new ConfigParameter('Hardcore', 'false', 'World'),
        ]);

        $file = $this->scaffolder()->scaffold('GameUserSettings.ini', 'ini', $parsed);

        self::assertArrayHasKey('ServerSettings', $file['parameters']);
        self::assertArrayHasKey('World', $file['parameters']);
        self::assertSame('text', $file['parameters']['ServerSettings']['Name']['display_type']);
        self::assertSame('number', $file['parameters']['ServerSettings']['Port']['display_type']);
        self::assertSame('boolean', $file['parameters']['World']['Hardcore']['display_type']);
    }

    public function test_it_derives_id_and_label_from_the_path(): void
    {
        $file = $this->scaffolder()->scaffold('plugins/config/bukkit.yml', 'yaml', new ParsedConfig([]));

        self::assertSame('bukkit-yml', $file['id']);
        self::assertSame('Bukkit', $file['label']['en']);
        self::assertSame([], $file['parameters']);
    }
}
