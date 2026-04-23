<?php

namespace App\Http\Controllers\Api;

use App\Events\AdminActionPerformed;
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
        $viewAll = $request->query('view') === 'all' && $request->user()->is_admin;

        if ($viewAll) {
            $servers = Server::query()
                ->with(['user:id,name,email', 'egg', 'plan'])
                ->orderBy('name')
                ->get();

            $enriched = $servers->map(function (Server $server) {
                $data = (new ServerResource($server))->toArray(request());
                $data['allocation'] = $server->identifier
                    ? $this->getCachedAllocation($server->identifier)
                    : null;
                $data['role'] = 'admin';
                $data['permissions'] = null;
                $data['owner'] = $server->user
                    ? ['id' => $server->user->id, 'name' => $server->user->name, 'email' => $server->user->email]
                    : null;

                return $data;
            });

            return response()->json(['data' => $enriched, 'meta' => ['view' => 'all']]);
        }

        $servers = $request->user()
            ->accessibleServers()
            ->with(['egg', 'plan'])
            ->orderBy('name')
            ->get();

        $enriched = $servers->map(function (Server $server) {
            $data = (new ServerResource($server))->toArray(request());
            $data['allocation'] = $server->identifier
                ? $this->getCachedAllocation($server->identifier)
                : null;
            $data['role'] = $server->pivot->role ?? 'owner';
            $data['permissions'] = $server->pivot->role === 'subuser'
                ? (is_string($server->pivot->permissions) ? json_decode($server->pivot->permissions, true) : $server->pivot->permissions)
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

        // Determine user's role and permissions for this server
        $permissions = $server->permissionsForUser($request->user());
        $role = $permissions === null ? 'owner' : 'subuser';

        return (new ServerResource($server))->additional([
            'allocation' => $allocation,
            'sftp_details' => $sftpDetails,
            'limits' => $limits,
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    public function startupVariables(Request $request, Server $server): JsonResponse
    {
        $this->authorize('readStartup', $server);
        $variables = $this->clientService->getStartupVariables($server->identifier);
        return response()->json(['data' => $variables]);
    }

    public function updateStartupVariable(Request $request, Server $server): JsonResponse
    {
        $this->authorize('updateStartup', $server);
        $validated = $request->validate(['key' => ['required', 'string'], 'value' => ['required', 'string']]);
        $this->clientService->updateStartupVariable($server->identifier, $validated['key'], $validated['value']);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.startup.update',
            server: $server,
            payload: ['key' => $validated['key'], 'value' => mb_substr($validated['value'], 0, 500)],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json(['success' => true]);
    }

    public function rename(Request $request, Server $server): JsonResponse
    {
        $this->authorize('renameServer', $server);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:191'],
        ]);

        $this->clientService->renameServer($server->identifier, $validated['name']);
        $server->update(['name' => $validated['name']]);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.rename',
            server: $server,
            payload: ['name' => $validated['name']],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json(['data' => new ServerResource($server->fresh())]);
    }

    public function reinstall(Request $request, Server $server): JsonResponse
    {
        $this->authorize('reinstallServer', $server);

        $this->clientService->reinstallServer($server->identifier);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.reinstall',
            server: $server,
            payload: [],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json(['success' => true]);
    }

    public function batchStats(Request $request): JsonResponse
    {
        $servers = $request->user()->accessibleServers()->whereNotNull('identifier')->get();
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
