<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Templates;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Templates\TemplateLoader;
use Plugins\EasyConfiguration\Services\Templates\TemplateSchemaValidator;
use Plugins\EasyConfiguration\Services\Templates\TemplateStorage;

final class OfficialTemplatesTest extends TestCase
{
    /** The official catalog the "Import official templates" button ships. */
    private const EXPECTED_IDS = [
        'ark-survival-ascended',
        'ark-survival-evolved',
        'astroneer',
        'hytale',
        'minecraft-paper',
        'palworld',
        'satisfactory',
        'sons-of-the-forest',
        'the-forest',
    ];

    public function test_every_official_template_is_valid_and_egg_agnostic(): void
    {
        $root = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'official';
        $loader = new TemplateLoader(new TemplateStorage($root, new Filesystem), new TemplateSchemaValidator);

        $loaded = $loader->loadAll();
        $ids = array_map(static fn ($t): string => $t->id, $loaded);
        sort($ids);

        self::assertSame(self::EXPECTED_IDS, $ids, 'The official catalog must hold exactly the nine shipped templates.');

        foreach ($loaded as $template) {
            self::assertTrue($template->valid, "Official template {$template->id} is invalid: {$template->error}");
            // The admin assigns the egg after importing — official templates must ship egg-agnostic.
            self::assertSame([], $template->definition?->targetEggs() ?? [null], "Official template {$template->id} must not target any egg.");
        }
    }
}
