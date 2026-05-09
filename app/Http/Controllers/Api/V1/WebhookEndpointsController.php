<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateWebhookEndpointRequest;
use App\Http\Resources\V1\WebhookEndpointResource;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Webhook endpoint CRUD for the authenticated shop. The signing_secret
 * is shown ONCE on create + on rotate-secret — never re-displayed.
 */
class WebhookEndpointsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        $endpoints = $shop->webhookEndpoints()->orderBy('id', 'desc')->get();

        return response()->json([
            'data' => WebhookEndpointResource::collection($endpoints)->toArray($request),
        ]);
    }

    public function store(CreateWebhookEndpointRequest $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        $secret = self::generateSecret();

        $endpoint = WebhookEndpoint::create([
            'shop_id' => $shop->id,
            'name' => $request->input('name'),
            'url' => $request->input('url'),
            'signing_secret' => $secret,
            'status' => 'active',
            'subscribed_events' => $request->input('subscribed_events'),
            'max_retries' => $request->input('max_retries', 5),
            'timeout_seconds' => $request->input('timeout_seconds', 30),
        ]);

        return response()->json([
            'data' => (new WebhookEndpointResource($endpoint))->toArray($request),
            'meta' => [
                'signing_secret' => $secret,
                'note' => 'This secret is shown ONCE. Store it now ; rotate via POST /rotate-secret if lost.',
            ],
        ], 201);
    }

    public function rotateSecret(Request $request, int $id): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        $endpoint = $shop->webhookEndpoints()->find($id);
        if ($endpoint === null) {
            return $this->notFound();
        }

        $secret = self::generateSecret();
        $endpoint->signing_secret = $secret;
        $endpoint->save();

        return response()->json([
            'data' => (new WebhookEndpointResource($endpoint->fresh()))->toArray($request),
            'meta' => [
                'signing_secret' => $secret,
                'note' => 'Old secret remains valid for in-flight deliveries until they retry. Update your verifier first.',
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        $endpoint = $shop->webhookEndpoints()->find($id);
        if ($endpoint === null) {
            return $this->notFound();
        }
        $endpoint->delete();
        return response()->json(null, 204);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'webhook_endpoint_not_found',
                'message' => __('api_v1.webhook_endpoint_not_found'),
            ],
        ], 404);
    }

    private static function generateSecret(): string
    {
        return 'whsec_'.bin2hex(random_bytes(24));
    }
}
