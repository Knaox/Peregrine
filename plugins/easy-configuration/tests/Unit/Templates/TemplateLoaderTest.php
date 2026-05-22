<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Templates;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Templates\TemplateLoader;
use Plugins\EasyConfiguration\Services\Templates\TemplateSchemaValidator;
use Plugins\EasyConfiguration\Services\Templates\TemplateStorage;

final class TemplateLoaderTest extends TestCase
{
    private string $root;

    private Filesystem $files;

    protected function setUp(): void
    {
        $this->files = new Filesystem;
        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ec-templates-'.uniqid();
        $this->files->ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->root);
    }

    private function loader(): TemplateLoader
    {
        return new TemplateLoader(
            new TemplateStorage($this->root, $this->files),
            new TemplateSchemaValidator,
        );
    }

    public function test_it_loads_a_valid_template_with_a_stable_checksum(): void
    {
        $json = json_encode([
            'id' => 'mc',
            'version' => '1.2.0',
            'name' => ['en' => 'MC'],
            'target_eggs' => [3],
            'files' => [[
                'id' => 'props', 'path' => 'server.properties', 'format' => 'properties',
                'parameters' => ['pvp' => ['display_type' => 'boolean', 'label' => ['en' => 'PvP']]],
            ]],
        ], JSON_PRETTY_PRINT);
        self::assertIsString($json);
        $this->files->put($this->root.DIRECTORY_SEPARATOR.'mc.json', $json);

        $loaded = $this->loader()->loadAll();

        self::assertCount(1, $loaded);
        self::assertTrue($loaded[0]->valid);
        self::assertSame('mc', $loaded[0]->id);
        self::assertSame([3], $loaded[0]->definition?->targetEggs());
        self::assertSame(sha1($json), $loaded[0]->checksum);
    }

    public function test_it_marks_invalid_json_as_invalid(): void
    {
        $this->files->put($this->root.DIRECTORY_SEPARATOR.'broken.json', '{ not json');

        $loaded = $this->loader()->loadOne('broken');

        self::assertNotNull($loaded);
        self::assertFalse($loaded->valid);
        self::assertSame('Invalid JSON document', $loaded->error);
    }

    public function test_it_captures_schema_errors(): void
    {
        $this->files->put(
            $this->root.DIRECTORY_SEPARATOR.'bad.json',
            (string) json_encode(['id' => 'bad', 'version' => '1.0.0', 'name' => ['en' => 'Bad'], 'target_eggs' => []]),
        );

        $loaded = $this->loader()->loadOne('bad');

        self::assertNotNull($loaded);
        self::assertFalse($loaded->valid);
        self::assertStringContainsString('files', (string) $loaded->error);
    }
}
