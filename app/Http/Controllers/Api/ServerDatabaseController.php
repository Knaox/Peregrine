<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\CreateDatabaseRequest;
use App\Models\Server;
use App\Services\Pelican\PelicanDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ServerDatabaseController extends Controller
{
    public function __construct(
        private PelicanDatabaseService $databaseService,
    ) {}

    public function index(Server $server): JsonResponse
    {
        $this->authorize('view', $server);

        $data = Cache::remember("server_databases:{$server->identifier}", 120, function () use ($server): array {
            $databases = $this->databaseService->listDatabases($server->identifier);

            return array_map(
                fn (array $db) => $db['attributes'] ?? $db,
                $databases,
            );
        });

        return response()->json(['data' => $data]);
    }

    public function store(CreateDatabaseRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->databaseService->createDatabase(
            $server->identifier,
            $validated['database'],
            $validated['remote'],
        );

        Cache::forget("server_databases:{$server->identifier}");

        return response()->json(['data' => $result], 201);
    }

    public function rotatePassword(Server $server, string $database): JsonResponse
    {
        $this->authorize('update', $server);

        $result = $this->databaseService->rotateDatabasePassword(
            $server->identifier,
            $database,
        );

        Cache::forget("server_databases:{$server->identifier}");

        return response()->json(['data' => $result]);
    }

    public function destroy(Server $server, string $database): JsonResponse
    {
        $this->authorize('update', $server);

        $this->databaseService->deleteDatabase($server->identifier, $database);

        Cache::forget("server_databases:{$server->identifier}");

        return response()->json(['message' => 'success']);
    }
}
