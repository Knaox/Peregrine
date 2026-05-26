<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PeregrinePlayerCounter\Services\QueryAccessResolver;
use Plugins\PeregrinePlayerCounter\Services\ServerPlayerCountService;

/**
 * Live connected-player count for a server. Mounted at
 * GET /api/plugins/peregrine-player-counter/servers/{server}/players.
 */
class PlayerCountController extends Controller
{
    public function __construct(private ServerPlayerCountService $players) {}

    public function show(Request $request, Server $server): JsonResponse
    {
        $this->authorize('view', $server);

        return response()->json([
            'data' => $this->players->get(
                $server,
                $request->boolean('refresh'),
                $request->boolean('running', true),
            ),
        ]);
    }

    /**
     * Manually trigger query-port resolution: allocate a port and point the
     * relevant startup variable at it (RCON, query, or the game port for
     * adjacent-port games), then restart. Destructive (restarts the server) —
     * gated by the `createAllocation` ability + a strict throttle; the SPA
     * confirms first. The same flow runs automatically on a failed query.
     */
    public function resolveRcon(Request $request, Server $server, QueryAccessResolver $resolver): JsonResponse
    {
        $this->authorize('createAllocation', $server);

        $result = $resolver->resolve($server);

        return response()->json($result, ($result['ok'] ?? false) === true ? 200 : 422);
    }
}
