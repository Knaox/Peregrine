<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-information for the authenticated shop. Useful for the SDK to
 * confirm which org/abilities the current key resolves to.
 */
class ShopMeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        $apiKey = $request->attributes->get('apiKey');

        return response()->json([
            'data' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'status' => $shop->status,
                'abilities' => $apiKey->abilities ?? [],
                'configurations_count' => $shop->serverConfigurations()->count(),
                'endpoints_count' => $shop->webhookEndpoints()->count(),
            ],
        ]);
    }
}
