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

        $key = "server_backups:{$server->identifier}";
        $data = Cache::get($key);

        if ($data === null) {
            $data = array_map(
                fn (array $backup): array => $backup['attributes'] ?? $backup,
                $this->backupService->listBackups($server->identifier),
            );

            // Adaptive TTL: while a backup is still running (no completed_at)
            // cache only briefly so the polling UI flips it to "done" within
            // seconds. Otherwise keep a short idle TTL too: backups created
            // OUTSIDE the panel (a scheduled backup task, or one made directly
            // on Pelican) never hit our store/destroy cache busting, so a long
            // TTL would hide them for minutes. 30s lets the page's idle poll
            // surface them quickly while staying well under Pelican's per-server
            // throttle (~2 req/min from this endpoint).
            $hasInProgress = collect($data)->contains(fn (array $b): bool => empty($b['completed_at']));
            Cache::put($key, $data, $hasInProgress ? 15 : 30);
        }

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
