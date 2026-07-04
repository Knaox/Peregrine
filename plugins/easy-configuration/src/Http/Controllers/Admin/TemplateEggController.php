<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers\Admin;

use App\Models\Egg;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Plugins\EasyConfiguration\Services\Pelican\EggBundleImporter;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use Plugins\EasyConfiguration\Services\Templates\TemplateStorage;

/**
 * "Import the egg into Pelican" button behind an official template. The egg
 * JSON ships with the plugin (`official/eggs/<template-id>.json`); importing
 * pushes it to Pelican (upsert by uuid — re-importing updates the existing
 * egg), re-syncs the local egg mirror, and attaches the resulting local egg id
 * to the template's `target_eggs` so the template is live without any manual
 * assignment step.
 */
final class TemplateEggController
{
    public function __construct(
        private readonly TemplateStorage $storage,
        private readonly TemplateRegistry $registry,
        private readonly EggBundleImporter $importer,
    ) {}

    public function import(string $id): JsonResponse
    {
        $path = self::bundlePath($id);
        $raw = $path !== null && is_file($path) ? (string) file_get_contents($path) : '';
        if ($raw === '' || ! is_array(json_decode($raw, true))) {
            return response()->json(['error' => ['code' => 'egg_bundle_missing', 'message' => 'No egg bundle ships with this template.']], 404);
        }

        try {
            $result = $this->importer->import($raw);
        } catch (RequestException $e) {
            report($e);

            return response()->json(['error' => ['code' => 'pelican_import_failed', 'message' => 'Pelican rejected the egg import.']], 502);
        }

        $attachedEggId = $result['pelican_egg_id'] !== null
            ? $this->attach($id, $result['pelican_egg_id'])
            : null;

        return response()->json(['data' => [
            'updated' => $result['updated'],
            'pelican_egg_id' => $result['pelican_egg_id'],
            'attached_egg_id' => $attachedEggId,
        ]]);
    }

    /**
     * Add the freshly imported egg (local mirror id) to the template's
     * `target_eggs`, when the template is present in the local store.
     */
    private function attach(string $id, int $pelicanEggId): ?int
    {
        $egg = Egg::query()->where('pelican_egg_id', $pelicanEggId)->first();
        if ($egg === null) {
            return null;
        }

        $raw = $this->storage->read($id);
        $template = $raw !== null ? json_decode($raw, true) : null;
        if (! is_array($template)) {
            return (int) $egg->id;
        }

        $targets = array_values(array_map('intval', (array) ($template['target_eggs'] ?? [])));
        if (! in_array((int) $egg->id, $targets, true)) {
            $targets[] = (int) $egg->id;
            $template['target_eggs'] = $targets;
            $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $this->storage->write($id, $json);
                $this->registry->rebuild();
            }
        }

        return (int) $egg->id;
    }

    /** Absolute path of the egg bundled for a template id, null on a hostile id. */
    public static function bundlePath(string $id): ?string
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $id) !== 1) {
            return null;
        }

        return dirname(__DIR__, 4).'/official/eggs/'.$id.'.json';
    }
}
