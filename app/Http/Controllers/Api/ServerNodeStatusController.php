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

/**
 * Node placement + Wings health for one server — powers the node name,
 * status pill and problem banner on the player server-home page.
 *
 * Backed by NodeHealthService's 30s cache so the SPA can poll gently
 * (~45s) without stampeding the daemon. Players only get the classified
 * status; raw Wings error bodies (request ids…) are admin-only.
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

        $report = $server->pelican_uuid !== null
            ? $health->checkServerOnNode($node, $server->pelican_uuid)
            : $health->checkNode($node);

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
