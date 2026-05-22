<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Templates;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Templates\TemplateLoader;
use Plugins\EasyConfiguration\Services\Templates\TemplateSchemaValidator;
use Plugins\EasyConfiguration\Services\Templates\TemplateStorage;

final class SampleTemplatesTest extends TestCase
{
    public function test_every_shipped_sample_template_is_valid(): void
    {
        $root = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'samples';
        $loader = new TemplateLoader(new TemplateStorage($root, new Filesystem), new TemplateSchemaValidator);

        $loaded = $loader->loadAll();

        self::assertNotEmpty($loaded, 'No sample templates were found.');
        foreach ($loaded as $sample) {
            self::assertTrue($sample->valid, "Sample {$sample->id} is invalid: {$sample->error}");
        }
    }
}
