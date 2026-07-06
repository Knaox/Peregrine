<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pelican\ResolveServerNodeAction;
use App\Enums\NodeHealthStatus;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Wings\DTOs\NodeHealthReport;
use App\Services\Wings\NodeHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Node placement + Wings health for one server — powers the node name,
 * status pill and problem banner on the player server-home page.
 *
 * Backed by NodeHealthService's 30s cache so the SPA can poll gently
 * (~45s) without stampeding the daemon. Players only get the classified
 * status; raw Wings error bodies (request ids…) are admin-only.
 *
 * This endpoint must NEVER 500: the node name is permanent UI (hero chip
 * + info card) and a crash would blank it. Any internal failure degrades
 * to health `unknown` (which renders no player banner) and gets logged.
 */
class ServerNodeStatusController extends Controller
{
    public function __invoke(
        Request $request,
        Server $server,
        ResolveServerNodeAction $resolveNode,
        NodeHealthService $health,
    ): JsonResponse {
        $this->authorize('view', $server);

        $node = $resolveNode($server);

        if ($node === null) {
            return response()->json([
                'node' => null,
                'health' => NodeHealthReport::make(NodeHealthStatus::Unknown)->toArray(),
            ]);
        }

        try {
            $report = $server->pelican_uuid !== null
                ? $health->checkServerOnNode($node, $server->pelican_uuid)
                : $health->checkNode($node);
        } catch (\Throwable $e) {
            Log::warning('ServerNodeStatus: health probe crashed — degrading to unknown', [
                'server_id' => $server->id,
                'node_id' => $node->id,
                'error' => $e->getMessage(),
            ]);
            $report = NodeHealthReport::make(NodeHealthStatus::Unknown, detail: $e->getMessage());
        }

        return response()->json([
            'node' => [
                'name' => $node->name,
                'location' => $node->location,
                'maintenance' => (bool) $node->maintenance_mode,
            ],
            'health' => $report->toArray(withDetail: (bool) $request->user()?->is_admin),
        ]);
    }
}
