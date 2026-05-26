<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PeregrinePlayerCounter\Services\RconResolver;
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
     * Auto-configure RCON: allocate a port, point the RCON startup variable at
     * it and restart the server. Destructive (restarts the server) — gated by
     * the `createAllocation` ability + a strict throttle; the SPA confirms first.
     */
    public function resolveRcon(Request $request, Server $server, RconResolver $resolver): JsonResponse
    {
        $this->authorize('createAllocation', $server);

        $result = $resolver->resolve($server);

        return response()->json($result, ($result['ok'] ?? false) === true ? 200 : 422);
    }
}
