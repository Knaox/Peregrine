<?php

namespace App\Http\Controllers\Api;

use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\CreateBackupRequest;
use App\Models\Server;
use App\Services\Pelican\PelicanBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServerBackupController extends Controller
{
    public function __construct(
        private PelicanBackupService $backupService,
    ) {}

    public function index(Server $server): JsonResponse
    {
        $this->authorize('readBackup', $server);

        $data = Cache::remember("server_backups:{$server->identifier}", 120, function () use ($server): array {
            $backups = $this->backupService->listBackups($server->identifier);

            return array_map(
                fn (array $backup) => $backup['attributes'] ?? $backup,
                $backups,
            );
        });

        return response()->json(['data' => $data]);
    }

    public function store(CreateBackupRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->backupService->createBackup(
            $server->identifier,
            $validated['name'] ?? null,
            $validated['ignored'] ?? null,
            $validated['is_locked'] ?? false,
        );

        Cache::forget("server_backups:{$server->identifier}");

        $this->audit($server, 'server.backup.create', [
            'name' => $validated['name'] ?? null,
            'is_locked' => $validated['is_locked'] ?? false,
        ]);

        return response()->json(['data' => $result], 201);
    }

    public function download(Server $server, string $backup): JsonResponse
    {
        $this->authorize('downloadBackup', $server);

        $url = $this->backupService->getBackupDownloadUrl(
            $server->identifier,
            $backup,
        );

        $this->audit($server, 'server.backup.download', ['backup' => $backup]);

        return response()->json(['data' => ['url' => $url]]);
    }

    public function toggleLock(Server $server, string $backup): JsonResponse
    {
        $this->authorize('deleteBackup', $server);

        $result = $this->backupService->toggleBackupLock(
            $server->identifier,
            $backup,
        );

        Cache::forget("server_backups:{$server->identifier}");

        $this->audit($server, 'server.backup.toggle_lock', ['backup' => $backup]);

        return response()->json(['data' => $result]);
    }

    public function restore(Request $request, Server $server, string $backup): JsonResponse
    {
        $this->authorize('restoreBackup', $server);

        $this->backupService->restoreBackup(
            $server->identifier,
            $backup,
            (bool) $request->input('truncate', false),
        );

        Cache::forget("server_backups:{$server->identifier}");

        $this->audit($server, 'server.backup.restore', [
            'backup' => $backup,
            'truncate' => (bool) $request->input('truncate', false),
        ]);

        return response()->json(['message' => 'success']);
    }

    public function destroy(Server $server, string $backup): JsonResponse
    {
        $this->authorize('deleteBackup', $server);

        $this->backupService->deleteBackup($server->identifier, $backup);

        Cache::forget("server_backups:{$server->identifier}");

        $this->audit($server, 'server.backup.delete', ['backup' => $backup]);

        return response()->json(['message' => 'success']);
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
