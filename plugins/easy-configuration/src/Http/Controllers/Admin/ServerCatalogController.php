<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers\Admin;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Server catalog for the template editor's "import from a server" picker:
 * id + name + the egg so the admin can pick a server that matches the egg the
 * template targets. Admin-gated by the route middleware.
 */
final class ServerCatalogController
{
    public function __construct(private readonly PelicanClientService $client) {}

    public function index(): JsonResponse
    {
        $servers = Server::query()
            ->leftJoin('eggs', 'eggs.id', '=', 'servers.egg_id')
            ->orderBy('servers.name')
            ->get([
                'servers.id',
                'servers.name',
                'servers.egg_id',
                'eggs.name as egg_name',
            ])
            ->map(static fn (Server $server): array => [
                'id' => (int) $server->id,
                'name' => (string) $server->name,
                'egg_id' => $server->egg_id !== null ? (int) $server->egg_id : null,
                'egg_name' => $server->egg_name !== null ? (string) $server->egg_name : null,
            ]);

        return response()->json(['data' => $servers]);
    }

    /**
     * Env var names for a server's egg, for the "link a parameter to an env var"
     * autocomplete. Returns each variable's env_variable name + current value.
     */
    public function envVars(Server $server): JsonResponse
    {
        if ($server->identifier === null || $server->identifier === '') {
            return response()->json(['error' => ['code' => 'server_unavailable', 'message' => __('easy-configuration::messages.import.server_unavailable')]], 422);
        }

        try {
            $variables = $this->client->getStartupVariables($server->identifier);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => ['code' => 'list_failed', 'message' => __('easy-configuration::messages.import.list_failed')]], 422);
        }

        $data = array_values(array_map(static fn (array $row): array => [
            'env_variable' => (string) ($row['env_variable'] ?? ''),
            'name' => (string) ($row['name'] ?? ($row['env_variable'] ?? '')),
            'server_value' => isset($row['server_value']) ? (string) $row['server_value'] : null,
        ], array_filter($variables, static fn (array $row): bool => ! empty($row['env_variable']))));

        return response()->json(['data' => $data]);
    }
}
