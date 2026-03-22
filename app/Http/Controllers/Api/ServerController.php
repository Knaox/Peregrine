<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServerController extends Controller
{
    public function __construct(
        private PelicanClientService $clientService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $servers = $request->user()
            ->servers()
            ->with(['egg', 'plan'])
            ->orderBy('name')
            ->get();

        return ServerResource::collection($servers);
    }

    public function show(Request $request, Server $server): ServerResource
    {
        $this->authorize('view', $server);

        $server->load(['egg', 'plan']);

        return new ServerResource($server);
    }

    public function startupVariables(Request $request, Server $server): JsonResponse
    {
        $this->authorize('view', $server);

        $variables = $this->clientService->getStartupVariables($server->identifier);

        return response()->json(['data' => $variables]);
    }

    public function updateStartupVariable(Request $request, Server $server): JsonResponse
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['required', 'string'],
        ]);

        $this->clientService->updateStartupVariable(
            $server->identifier,
            $validated['key'],
            $validated['value'],
        );

        return response()->json(['success' => true]);
    }

    public function batchStats(Request $request): JsonResponse
    {
        $servers = $request->user()
            ->servers()
            ->whereNotNull('identifier')
            ->get();

        $stats = [];
        foreach ($servers as $server) {
            try {
                $resources = $this->clientService->getServerResources($server->identifier);
                $stats[$server->id] = [
                    'state' => $resources->state,
                    'cpu' => $resources->cpuAbsolute,
                    'memory_bytes' => $resources->memoryBytes,
                    'disk_bytes' => $resources->diskBytes,
                    'network_rx' => $resources->networkRxBytes,
                    'network_tx' => $resources->networkTxBytes,
                ];
            } catch (\Throwable) {
                $stats[$server->id] = [
                    'state' => 'offline',
                    'cpu' => 0,
                    'memory_bytes' => 0,
                    'disk_bytes' => 0,
                    'network_rx' => 0,
                    'network_tx' => 0,
                ];
            }
        }

        return response()->json(['data' => $stats]);
    }
}
