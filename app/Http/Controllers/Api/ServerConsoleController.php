<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\CommandRequest;
use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerConsoleController extends Controller
{
    public function __construct(
        private PelicanClientService $clientService,
    ) {}

    public function command(CommandRequest $request, Server $server): JsonResponse
    {
        $this->clientService->sendCommand(
            $server->identifier,
            $request->validated('command'),
        );

        return response()->json(['success' => true]);
    }

    public function websocket(Request $request, Server $server): JsonResponse
    {
        // The WebSocket is a multi-purpose channel (console + stats). Any user
        // with server access may open it; content-level gating (console view,
        // command send, stats) is enforced by the frontend and the dedicated
        // command endpoint (which requires control.console).
        $this->authorize('view', $server);

        $credentials = $this->clientService->getWebsocket($server->identifier);

        return response()->json([
            'data' => [
                'token' => $credentials->token,
                'socket' => $credentials->socket,
            ],
        ]);
    }

    public function resources(Request $request, Server $server): JsonResponse
    {
        $this->authorize('readStats', $server);

        $resources = $this->clientService->getServerResources($server->identifier);

        return response()->json([
            'data' => [
                'state' => $resources->state,
                'cpu' => $resources->cpuAbsolute,
                'memory_bytes' => $resources->memoryBytes,
                'disk_bytes' => $resources->diskBytes,
                'network_rx' => $resources->networkRxBytes,
                'network_tx' => $resources->networkTxBytes,
            ],
        ]);
    }
}
