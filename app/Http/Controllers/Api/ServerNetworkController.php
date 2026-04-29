<?php

namespace App\Http\Controllers\Api;

use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Pelican\PelicanNetworkService;
use App\Services\SettingsService;
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

        if ($this->mirrorReadsEnabled()) {
            $rows = $server->pelicanAllocations()
                ->orderBy('port')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->pelican_allocation_id,
                    'ip' => $a->ip,
                    'ip_alias' => $a->ip_alias,
                    'port' => $a->port,
                    'notes' => $a->notes,
                    'is_default' => false,
                ])
                ->all();

            return response()->json(['data' => $rows]);
        }

        $data = Cache::remember("server_network:{$server->identifier}", 600, function () use ($server): array {
            $allocations = $this->networkService->listAllocations($server->identifier);

            return array_map(
                fn (array $allocation) => $allocation['attributes'] ?? $allocation,
                $allocations,
            );
        });

        return response()->json(['data' => $data]);
    }

    private function mirrorReadsEnabled(): bool
    {
        $value = (string) app(SettingsService::class)->get('mirror_reads_enabled', 'false');
        return $value === 'true' || $value === '1';
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

        $this->audit($server, 'server.network.add_allocation', []);

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

        $this->audit($server, 'server.network.update_notes', ['allocation_id' => $allocation]);

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

        $this->audit($server, 'server.network.set_primary', ['allocation_id' => $allocation]);

        return response()->json(['data' => $result]);
    }

    public function destroy(Server $server, int $allocation): JsonResponse
    {
        $this->authorize('deleteAllocation', $server);

        $this->networkService->deleteAllocation($server->identifier, $allocation);

        Cache::forget("server_network:{$server->identifier}");
        Cache::forget("server_allocation:{$server->identifier}");

        $this->audit($server, 'server.network.delete_allocation', ['allocation_id' => $allocation]);

        return response()->json(['message' => 'success']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(Server $server, string $action, array $payload): void
    {
        $req = request();
        AdminActionPerformed::dispatchIfCrossUser(
            admin: $req->user(),
            action: $action,
            server: $server,
            payload: $payload,
            ip: $req->ip(),
            userAgent: (string) $req->userAgent(),
        );
    }
}
