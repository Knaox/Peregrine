<?php

namespace App\Http\Controllers\Api;

use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\CreateFolderRequest;
use App\Http\Requests\Server\FileChmodRequest;
use App\Http\Requests\Server\FileCompressRequest;
use App\Http\Requests\Server\FileDecompressRequest;
use App\Http\Requests\Server\FileDeleteRequest;
use App\Http\Requests\Server\FilePullRequest;
use App\Http\Requests\Server\FileRenameRequest;
use App\Http\Requests\Server\FileWriteRequest;
use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ServerFileController extends Controller
{
    public function __construct(
        private PelicanFileService $clientService,
    ) {}

    public function list(Request $request, Server $server): JsonResponse
    {
        $this->authorize('readFile', $server);

        $directory = $request->query('directory', '/');
        $rawFiles = $this->clientService->listFiles($server->identifier, $directory);

        // Pelican returns [{ "object": "file_object", "attributes": { ... } }]
        // Flatten and ensure is_directory is set (Pelican may not include it)
        $files = array_map(function (array $file): array {
            $attrs = $file['attributes'] ?? $file;
            if (!isset($attrs['is_directory'])) {
                $attrs['is_directory'] = ($attrs['mimetype'] ?? '') === 'inode/directory'
                    || (isset($attrs['is_file']) && $attrs['is_file'] === false);
            }
            return $attrs;
        }, $rawFiles);

        return response()->json(['data' => $files]);
    }

    public function content(Request $request, Server $server): Response
    {
        $this->authorize('readFileContent', $server);

        $file = $request->query('file');
        if (!$file) {
            return response('File path required', 422);
        }

        $content = $this->clientService->getFileContent($server->identifier, $file);

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }

    public function write(FileWriteRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->clientService->writeFile(
                $server->identifier,
                $validated['file'],
                // `content` arrives as null when the player submitted "" —
                // `ConvertEmptyStringsToNull` middleware did its work.
                // Coerce back to a string so the Pelican client gets a
                // valid empty body (creates an empty file).
                (string) ($validated['content'] ?? ''),
            );
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Surface the real Pelican / Wings error instead of letting it
            // bubble up as a generic 500. The frontend's API error layer
            // shows the body, so the operator sees what went wrong.
            \Log::warning('writeFile: Pelican returned an error', [
                'server' => $server->identifier,
                'file' => $validated['file'],
                'content_length' => strlen($validated['content']),
                'pelican_status' => $e->response?->status(),
                'pelican_body' => $e->response?->body(),
            ]);
            return response()->json([
                'error' => 'pelican_rejected',
                'pelican_status' => $e->response?->status(),
                'pelican_body' => $e->response?->json() ?? $e->response?->body(),
            ], $e->response?->status() ?? 500);
        }

        $this->audit($request, $server, 'server.file.write', ['file' => $validated['file']]);

        return response()->json(['success' => true]);
    }

    public function rename(FileRenameRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        // PelicanClientService->renameFile expects from/to for a single file
        // But Pelican API supports batch rename. We'll call the raw API.
        foreach ($validated['files'] as $file) {
            $this->clientService->renameFile(
                $server->identifier,
                $validated['root'] . '/' . $file['from'],
                $file['to'],
            );
        }

        $this->audit($request, $server, 'server.file.rename', ['root' => $validated['root'], 'count' => count($validated['files'])]);

        return response()->json(['success' => true]);
    }

    public function delete(FileDeleteRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        foreach ($validated['files'] as $file) {
            $this->clientService->deleteFile(
                $server->identifier,
                $validated['root'] . '/' . $file,
            );
        }

        $this->audit($request, $server, 'server.file.delete', ['root' => $validated['root'], 'count' => count($validated['files'])]);

        return response()->json(['success' => true]);
    }

    public function compress(FileCompressRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        $this->clientService->compressFiles($server->identifier, $validated['files']);

        $this->audit($request, $server, 'server.file.compress', ['count' => count($validated['files'])]);

        return response()->json(['success' => true]);
    }

    public function decompress(FileDecompressRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        $this->clientService->decompressFiles(
            $server->identifier,
            $validated['root'] . '/' . $validated['file'],
        );

        $this->audit($request, $server, 'server.file.decompress', ['file' => $validated['file']]);

        return response()->json(['success' => true]);
    }

    public function uploadUrl(Request $request, Server $server): JsonResponse
    {
        $this->authorize('createFile', $server);

        $url = $this->clientService->getUploadUrl($server->identifier);

        return response()->json(['data' => ['url' => $url]]);
    }

    public function createFolder(CreateFolderRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        $this->clientService->createFolder(
            $server->identifier,
            $validated['root'],
            $validated['name'],
        );

        $this->audit($request, $server, 'server.file.create_folder', ['root' => $validated['root'], 'name' => $validated['name']]);

        return response()->json(['success' => true]);
    }

    public function download(Request $request, Server $server): JsonResponse
    {
        $this->authorize('readFile', $server);

        $file = $request->query('file');
        if (!$file) {
            return response()->json(['error' => 'File path required'], 422);
        }

        $url = $this->clientService->getFileDownloadUrl($server->identifier, (string) $file);

        $this->audit($request, $server, 'server.file.download', ['file' => (string) $file]);

        return response()->json(['data' => ['url' => $url]]);
    }

    public function copy(Request $request, Server $server): JsonResponse
    {
        $this->authorize('createFile', $server);

        $location = $request->input('location');
        if (!$location) {
            return response()->json(['error' => 'Location required'], 422);
        }

        $this->clientService->copyFile($server->identifier, $location);

        $this->audit($request, $server, 'server.file.copy', ['location' => (string) $location]);

        return response()->json(['message' => 'success']);
    }

    public function chmod(FileChmodRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        // Normalize mode: accept "755" / "0755" / 493 — Pelican expects integer.
        $files = array_map(static function (array $f): array {
            $mode = $f['mode'];
            if (is_string($mode)) {
                $mode = (int) octdec(ltrim($mode, '0') ?: '0');
            }
            return ['file' => $f['file'], 'mode' => (int) $mode];
        }, $validated['files']);

        $this->clientService->chmodFiles($server->identifier, $validated['root'], $files);

        $this->audit($request, $server, 'server.file.chmod', ['root' => $validated['root'], 'count' => count($files)]);

        return response()->json(['success' => true]);
    }

    public function pull(FilePullRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        $this->clientService->pullFile(
            $server->identifier,
            $validated['url'],
            $validated['directory'] ?? null,
            $validated['filename'] ?? null,
        );

        $this->audit($request, $server, 'server.file.pull', [
            'url' => mb_substr($validated['url'], 0, 500),
            'directory' => $validated['directory'] ?? null,
            'filename' => $validated['filename'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(Request $request, Server $server, string $action, array $payload): void
    {
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
