<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers\Admin;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Lists a server's directory contents for the template editor's "import from a
 * server" file browser. Admin-gated by the route middleware (browses ANY
 * server), distinct from the core per-server `file.read` endpoint. Wraps
 * Pelican's live file listing; nothing is written.
 */
final class ServerFileBrowserController
{
    public function __construct(private readonly PelicanFileService $files) {}

    public function index(Request $request, Server $server): JsonResponse
    {
        if ($server->identifier === null || $server->identifier === '') {
            return $this->error('server_unavailable', __('easy-configuration::messages.import.server_unavailable'));
        }

        $directory = (string) $request->query('directory', '/');

        try {
            $raw = $this->files->listFiles($server->identifier, $directory);
        } catch (Throwable $e) {
            report($e);

            return $this->error('list_failed', __('easy-configuration::messages.import.list_failed'));
        }

        return response()->json(['data' => self::normalizeEntries($raw)]);
    }

    /**
     * Flatten Pelican's `{ object, attributes }` envelope and make sure every
     * entry carries an `is_directory` flag — Pelican omits it, so derive it from
     * the mimetype or the `is_file` flag (mirrors the core ServerFileController).
     *
     * Pure + static so it can be unit-tested without booting Laravel.
     *
     * @param  array<int, array<string, mixed>>  $raw
     * @return list<array<string, mixed>>
     */
    public static function normalizeEntries(array $raw): array
    {
        return array_values(array_map(static function (array $file): array {
            /** @var array<string, mixed> $attrs */
            $attrs = $file['attributes'] ?? $file;

            if (! isset($attrs['is_directory'])) {
                $attrs['is_directory'] = ($attrs['mimetype'] ?? '') === 'inode/directory'
                    || (isset($attrs['is_file']) && $attrs['is_file'] === false);
            }

            return $attrs;
        }, $raw));
    }

    private function error(string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], 422);
    }
}
