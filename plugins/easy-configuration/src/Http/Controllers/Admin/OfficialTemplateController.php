<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use Plugins\EasyConfiguration\Services\Templates\TemplateSchemaValidator;
use Plugins\EasyConfiguration\Services\Templates\TemplateStorage;

/**
 * One-click import of the bundled official template catalog (plugin `official/`
 * dir) into the on-disk template store. Templates ship egg-agnostic — the admin
 * assigns the target egg afterwards — and an existing template of the same id is
 * left untouched (skip-if-exists) so admin customisations are never clobbered.
 */
final class OfficialTemplateController
{
    /** The exact catalog the button ships — nothing else from the plugin is imported. */
    private const OFFICIAL_IDS = [
        '7-days-to-die',
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

    public function __construct(
        private readonly TemplateStorage $storage,
        private readonly TemplateRegistry $registry,
        private readonly TemplateSchemaValidator $validator,
    ) {}

    public function import(): JsonResponse
    {
        $dir = dirname(__DIR__, 4).'/official';
        $imported = [];
        $skipped = [];
        $errors = [];

        foreach (self::OFFICIAL_IDS as $id) {
            $path = $dir.'/'.$id.'.json';
            $raw = is_file($path) ? (string) file_get_contents($path) : '';
            $template = $raw !== '' ? json_decode($raw, true) : null;
            if (! is_array($template)) {
                $errors[] = "{$id}: missing or unreadable official template";

                continue;
            }

            // Ship egg-agnostic regardless of the bundled file's contents.
            $template['target_eggs'] = [];

            $validationErrors = $this->validator->validate($template);
            if ($validationErrors !== []) {
                $errors[] = "{$id}: ".implode('; ', $validationErrors);

                continue;
            }

            // Never overwrite an admin-customised template of the same id.
            if ($this->storage->exists($id)) {
                $skipped[] = $id;

                continue;
            }

            $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false || $this->storage->path($id) === null) {
                $errors[] = "{$id}: unable to serialise";

                continue;
            }

            $this->storage->write($id, $json);
            $imported[] = $id;
        }

        $this->registry->rebuild();

        if ($errors !== []) {
            return response()->json(['error' => ['code' => 'official_import_failed', 'messages' => $errors]], 422);
        }

        return response()->json(['data' => ['imported' => $imported, 'skipped' => $skipped]]);
    }
}
