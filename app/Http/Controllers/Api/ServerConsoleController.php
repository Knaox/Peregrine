<?php

namespace App\Http\Controllers\Api;

use App\Events\AdminActionPerformed;
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
        $command = (string) $request->validated('command');
        $this->clientService->sendCommand($server->identifier, $command);

        // Truncate the stored command — plan §S6: a malicious admin could
        // flood the audit table with multi-MB payloads otherwise.
        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.command',
            server: $server,
            payload: ['command' => mb_substr($command, 0, 500)],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
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

        try {
            $credentials = $this->clientService->getWebsocket($server->identifier);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Pelican throttles its Client API by default (60/min). An admin
            // rapidly browsing multiple servers via admin mode will hit this.
            // Surface a clean status upstream instead of a 500 stacktrace.
            $status = $e->response?->status() ?? 502;
            $code = match ($status) {
                429 => 'servers.websocket.pelican_throttled',
                403, 404 => 'servers.websocket.pelican_denied',
                default => 'servers.websocket.pelican_unavailable',
            };

            return response()->json(
                ['error' => $code],
                $status === 429 ? 429 : 503,
            );
        }

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.console.stream',
            server: $server,
            payload: [],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

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
