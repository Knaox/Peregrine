<?php

namespace App\Http\Controllers\Api;

use App\Events\AdminActionPerformed;
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
        $this->authorize('readDatabase', $server);

        $data = Cache::remember("server_databases:{$server->identifier}", 120, function () use ($server): array {
            $databases = $this->databaseService->listDatabases($server->identifier);

            return array_map(
                fn (array $db) => $db['attributes'] ?? $db,
                $databases,
            );
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Live fetch of the database list including the plaintext password.
     * Never cached, never persisted. Triggered when the user clicks
     * "Show password" on the SPA. Always hits Pelican Client API.
     */
    public function credentials(Server $server, string $database): JsonResponse
    {
        $this->authorize('viewDatabasePassword', $server);

        $databases = $this->databaseService->listDatabases($server->identifier, includePassword: true);

        foreach ($databases as $row) {
            $attrs = $row['attributes'] ?? $row;
            $attrId = (string) ($attrs['id'] ?? '');
            if ($attrId === $database) {
                $this->audit($server, 'server.database.show_credentials', ['database' => $database]);

                return response()->json(['data' => $this->flattenPassword($attrs)]);
            }
        }

        return response()->json(['error' => 'database_not_found'], 404);
    }

    public function store(CreateDatabaseRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->flattenPassword($this->databaseService->createDatabase(
            $server->identifier,
            $validated['database'],
            $validated['remote'],
        ));

        Cache::forget("server_databases:{$server->identifier}");

        $this->audit($server, 'server.database.create', [
            'database' => $validated['database'],
            'remote' => $validated['remote'],
        ]);

        return response()->json(['data' => $result], 201);
    }

    public function rotatePassword(Server $server, string $database): JsonResponse
    {
        $this->authorize('updateDatabase', $server);

        $result = $this->flattenPassword($this->databaseService->rotateDatabasePassword(
            $server->identifier,
            $database,
        ));

        Cache::forget("server_databases:{$server->identifier}");

        $this->audit($server, 'server.database.rotate_password', ['database' => $database]);

        return response()->json(['data' => $result]);
    }

    public function destroy(Server $server, string $database): JsonResponse
    {
        $this->authorize('deleteDatabase', $server);

        $this->databaseService->deleteDatabase($server->identifier, $database);

        Cache::forget("server_databases:{$server->identifier}");

        $this->audit($server, 'server.database.delete', ['database' => $database]);

        return response()->json(['message' => 'success']);
    }

    /**
     * Pelican nests the plaintext password under
     * `relationships.password.attributes.password` (create + rotate responses,
     * and list calls made with `?include=password`). Flatten it to a top-level
     * `password` string so the SPA can display it, then drop the relationships
     * envelope. No-op when no password is present.
     *
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function flattenPassword(array $attrs): array
    {
        $password = $attrs['relationships']['password']['attributes']['password']
            ?? $attrs['password']
            ?? null;

        if ($password !== null) {
            $attrs['password'] = $password;
        }

        unset($attrs['relationships']);

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(Server $server, string $action, array $payload): void
    {
        $request = request();
        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: $action,
            server: $server,
            payload: $payload,
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );
    }
}
