<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers\Admin;

use App\Models\Egg;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Egg catalog for the template editor's egg picker: id + name + the same
 * banner image URL the core server cards render, so the admin sees each egg
 * with its artwork when choosing `target_eggs`. Also serves each egg's startup
 * variable names for the "link a parameter to an env var" autocomplete.
 */
final class EggCatalogController
{
    public function __construct(private readonly PelicanApplicationService $pelican) {}

    public function index(): JsonResponse
    {
        $eggs = Egg::query()
            ->orderBy('name')
            ->get(['id', 'name', 'banner_image'])
            ->map(static fn (Egg $egg): array => [
                'id' => $egg->id,
                'name' => $egg->name,
                'banner_image' => $egg->banner_image ? asset('storage/'.$egg->banner_image) : null,
            ]);

        return response()->json(['data' => $eggs]);
    }

    /**
     * Env variable names for an egg, fetched from Pelican's Application API, for
     * the template editor's "link to env var" autocomplete. Pointing at an egg
     * (not a live server) is more convenient: the admin links names while
     * authoring, before any server exists.
     */
    public function envVars(Egg $egg): JsonResponse
    {
        try {
            $variables = $this->pelican->getEggVariables($egg->pelican_egg_id);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => ['code' => 'list_failed', 'message' => __('easy-configuration::messages.import.list_failed')]], 422);
        }

        return response()->json(['data' => $variables]);
    }
}
