<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers\Admin;

use App\Models\Egg;
use Illuminate\Http\JsonResponse;

/**
 * Egg catalog for the template editor's egg picker: id + name + the same
 * banner image URL the core server cards render, so the admin sees each egg
 * with its artwork when choosing `target_eggs`.
 */
final class EggCatalogController
{
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
}
