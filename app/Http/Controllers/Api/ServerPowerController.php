<?php

namespace App\Http\Controllers\Api;

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
        $this->clientService->setPowerState(
            $server->identifier,
            $request->validated('signal'),
        );

        return response()->json(['success' => true]);
    }
}
