<?php

namespace App\Http\Controllers\Api;

use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\PowerRequest;
use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use Illuminate\Http\JsonResponse;

class ServerPowerController extends Controller
{
    public function __construct(
        private PelicanClientService $clientService,
    ) {}

    public function __invoke(PowerRequest $request, Server $server): JsonResponse
    {
        $signal = $request->validated('signal');
        $this->clientService->setPowerState($server->identifier, $signal);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.power.'.$signal,
            server: $server,
            payload: ['signal' => $signal],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json(['success' => true]);
    }
}
