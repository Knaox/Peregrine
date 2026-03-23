<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Pelican\PelicanNetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServerNetworkController extends Controller
{
    public function __construct(
        private PelicanNetworkService $networkService,
    ) {}

    public function index(Server $server): JsonResponse
    {
        $this->authorize('view', $server);

        $data = Cache::remember("server_network:{$server->identifier}", 600, function () use ($server): array {
            $allocations = $this->networkService->listAllocations($server->identifier);

            return array_map(
                fn (array $allocation) => $allocation['attributes'] ?? $allocation,
                $allocations,
            );
        });

        return response()->json(['data' => $data]);
    }

    public function store(Server $server): JsonResponse
    {
        $this->authorize('update', $server);

        // Check current allocation count before calling Pelican
        $current = $this->networkService->listAllocations($server->identifier);
        $currentCount = count($current);

        // Get server limits from Pelican to check allocation_limit
        try {
            $raw = app(\App\Services\Pelican\PelicanClientService::class)->getRawServer($server->identifier);
            $limit = $raw['feature_limits']['allocations'] ?? 0;
        } catch (\Throwable) {
            $limit = 0;
        }

        if ($limit > 0 && $currentCount >= $limit) {
            return response()->json(['message' => 'allocation_limit_reached'], 422);
        }

        try {
            $result = $this->networkService->addAllocation($server->identifier);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response->status() === 429) {
                return response()->json(['message' => 'rate_limited'], 429);
            }
            return response()->json(['message' => 'no_allocations'], 422);
        }

        Cache::forget("server_network:{$server->identifier}");
        Cache::forget("server_allocation:{$server->identifier}");

        return response()->json(['data' => $result['attributes'] ?? $result]);
    }

    public function updateNotes(Request $request, Server $server, int $allocation): JsonResponse
    {
        $this->authorize('update', $server);

        $result = $this->networkService->updateAllocationNotes(
            $server->identifier,
            $allocation,
            $request->input('notes', ''),
        );

        Cache::forget("server_network:{$server->identifier}");

        return response()->json(['data' => $result]);
    }

    public function setPrimary(Server $server, int $allocation): JsonResponse
    {
        $this->authorize('update', $server);

        $result = $this->networkService->setPrimaryAllocation(
            $server->identifier,
            $allocation,
        );

        Cache::forget("server_network:{$server->identifier}");
        Cache::forget("server_allocation:{$server->identifier}");

        return response()->json(['data' => $result]);
    }

    public function destroy(Server $server, int $allocation): JsonResponse
    {
        $this->authorize('update', $server);

        $this->networkService->deleteAllocation($server->identifier, $allocation);

        Cache::forget("server_network:{$server->identifier}");
        Cache::forget("server_allocation:{$server->identifier}");

        return response()->json(['message' => 'success']);
    }
}
