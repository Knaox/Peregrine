<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ConfigurationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalog endpoints for the authenticated shop. Configurations are
 * scoped via the `shop_server_configuration` pivot — orphan
 * configurations are invisible.
 *
 * `index` returns up to 50 rows per cursor page (cursorPaginate gives
 * an opaque base64 token consumers reuse without parsing).
 */
class ConfigurationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');

        $configurations = $shop->serverConfigurations()
            ->wherePivot('is_visible', true)
            ->orderByPivot('sort_order')
            ->cursorPaginate(50);

        return response()->json([
            'data' => ConfigurationResource::collection($configurations->items())->toArray($request),
            'meta' => [
                'next_cursor' => $configurations->nextCursor()?->encode(),
                'prev_cursor' => $configurations->previousCursor()?->encode(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $shop = $request->attributes->get('shop');

        $configuration = $shop->serverConfigurations()
            ->where('server_configurations.id', $id)
            ->wherePivot('is_visible', true)
            ->first();

        if ($configuration === null) {
            return response()->json([
                'error' => [
                    'code' => 'configuration_not_found',
                    'message' => __('api_v1.configuration_not_found'),
                ],
            ], 404);
        }

        return response()->json([
            'data' => (new ConfigurationResource($configuration))->toArray($request),
        ]);
    }
}
