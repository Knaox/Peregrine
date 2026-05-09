<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Order tracking. Shop polls this endpoint with the `external_order_id`
 * it embedded in Stripe metadata to learn the asynchronous state of
 * the resulting `Server` row (provisioning → active → suspended …).
 *
 * Scoping : the lookup intersects on `external_order_id` AND the
 * server's configuration must belong to the requesting shop's pivot.
 * A shop can never see another shop's order even if the strings happen
 * to collide.
 */
class OrdersController extends Controller
{
    public function show(Request $request, string $externalOrderId): JsonResponse
    {
        $shop = $request->attributes->get('shop');

        $server = Server::query()
            ->where('external_order_id', $externalOrderId)
            ->whereHas('serverConfiguration.shops', fn ($q) => $q->where('shops.id', $shop->id))
            ->first();

        if ($server === null) {
            return response()->json([
                'error' => [
                    'code' => 'order_not_found',
                    'message' => __('api_v1.order_not_found'),
                ],
            ], 404);
        }

        return response()->json([
            'data' => (new OrderResource($server))->toArray($request),
        ]);
    }
}
