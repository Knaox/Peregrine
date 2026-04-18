<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use App\Services\Pelican\PelicanNetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServerController extends Controller
{
    public function __construct(
        private PelicanClientService $clientService,
        private PelicanNetworkService $networkService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $servers = $request->user()
            ->servers()
            ->with(['egg', 'plan'])
            ->orderBy('name')
            ->get();

        $enriched = $servers->map(function (Server $server) {
            $data = (new ServerResource($server))->toArray(request());
            $data['allocation'] = $server->identifier
                ? $this->getCachedAllocation($server->identifier)
                : null;
            return $data;
        });

        return response()->json(['data' => $enriched]);
    }

    public function show(Request $request, Server $server): ServerResource
    {
        $this->authorize('view', $server);
        $server->load(['egg', 'plan']);

        $allocation = null;
        $sftpDetails = null;

        $limits = null;

        if ($server->identifier) {
            $allocation = $this->getCachedAllocation($server->identifier);

            // Clear legacy cache key from before refactor
            Cache::forget("sftp_details:{$server->identifier}");

            $cacheKey = "server_raw_v2:{$server->identifier}";
            $rawData = Cache::get($cacheKey);
            if ($rawData === null) {
                try {
                    $rawData = $this->clientService->getRawServer($server->identifier);
                    if (!empty($rawData)) {
                        Cache::put($cacheKey, $rawData, 900);
                    }
                } catch (\Throwable) {
                    $rawData = [];
                }
            }

            $sftpRaw = $rawData['sftp_details'] ?? [];
            $pelicanUsername = strtolower($request->user()->name ?? 'user');
            $sftpDetails = [
                'ip' => $sftpRaw['alias'] ?? $sftpRaw['ip'] ?? $allocation['ip'] ?? null,
                'port' => $sftpRaw['port'] ?? 2022,
                'username' => $pelicanUsername . '.' . $server->identifier,
            ];

            // Extract server limits from Pelican API response
            $rawLimits = $rawData['limits'] ?? [];
            if (!empty($rawLimits)) {
                $limits = [
                    'memory' => $rawLimits['memory'] ?? 0,
                    'cpu' => $rawLimits['cpu'] ?? 0,
                    'disk' => $rawLimits['disk'] ?? 0,
                ];
            }
        }

        return (new ServerResource($server))->additional([
            'allocation' => $allocation,
            'sftp_details' => $sftpDetails,
            'limits' => $limits,
        ]);
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
        $validated = $request->validate(['key' => ['required', 'string'], 'value' => ['required', 'string']]);
        $this->clientService->updateStartupVariable($server->identifier, $validated['key'], $validated['value']);
        return response()->json(['success' => true]);
    }

    public function batchStats(Request $request): JsonResponse
    {
        $servers = $request->user()->servers()->whereNotNull('identifier')->get();
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
                    'uptime' => $resources->uptime,
                ];
            } catch (\Throwable) {
                $stats[$server->id] = ['state' => 'offline', 'cpu' => 0, 'memory_bytes' => 0, 'disk_bytes' => 0, 'network_rx' => 0, 'network_tx' => 0, 'uptime' => 0];
            }
        }

        return response()->json(['data' => $stats]);
    }

    /**
     * Get server primary allocation from cache (Redis 10min TTL).
     */
    private function getCachedAllocation(string $identifier): ?array
    {
        return Cache::remember("server_allocation:{$identifier}", 600, function () use ($identifier) {
            try {
                $allocations = $this->networkService->listAllocations($identifier);
                foreach ($allocations as $alloc) {
                    $attrs = $alloc['attributes'] ?? $alloc;
                    if ($attrs['is_default'] ?? false) {
                        return ['ip' => $attrs['ip_alias'] ?? $attrs['ip'], 'port' => $attrs['port']];
                    }
                }
                if (count($allocations) > 0) {
                    $attrs = $allocations[0]['attributes'] ?? $allocations[0];
                    return ['ip' => $attrs['ip_alias'] ?? $attrs['ip'], 'port' => $attrs['port']];
                }
            } catch (\Throwable) {
                // Pelican unreachable
            }
            return null;
        });
    }
}
