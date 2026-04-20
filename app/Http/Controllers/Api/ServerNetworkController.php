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
        $this->authorize('readAllocation', $server);

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
        $this->authorize('createAllocation', $server);

        try {
            $result = $this->networkService->addAllocation($server->identifier);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $status = $e->response->status();
            $detail = $e->response->json('errors.0.detail') ?? '';
            return response()->json(['message' => $detail ?: 'Failed to add allocation.'], $status >= 400 ? $status : 422);
        }

        Cache::forget("server_network:{$server->identifier}");
        Cache::forget("server_allocation:{$server->identifier}");

        return response()->json(['data' => $result['attributes'] ?? $result]);
    }

    public function updateNotes(Request $request, Server $server, int $allocation): JsonResponse
    {
        $this->authorize('updateAllocation', $server);

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
        $this->authorize('updateAllocation', $server);

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
        $this->authorize('deleteAllocation', $server);

        $this->networkService->deleteAllocation($server->identifier, $allocation);

        Cache::forget("server_network:{$server->identifier}");
        Cache::forget("server_allocation:{$server->identifier}");

        return response()->json(['message' => 'success']);
    }
}
