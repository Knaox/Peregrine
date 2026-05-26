<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers\Admin;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Illuminate\Http\JsonResponse;
use Plugins\EasyConfiguration\Http\Requests\ImportConfigRequest;
use Plugins\EasyConfiguration\Services\Import\ConfigImportScaffolder;
use Plugins\EasyConfiguration\Services\Parsing\ParserRegistry;
use Throwable;

/**
 * Imports a real config file from an existing server and scaffolds a template
 * `file` block from it (paths + current values + guessed display types). The
 * admin then refines display types and adds FR/EN labels in the editor. Reading
 * is live via Pelican; nothing is written and the template stores no values.
 *
 * Admin-gated by the route middleware.
 */
final class ImportConfigController
{
    public function __invoke(
        ImportConfigRequest $request,
        ParserRegistry $parsers,
        ConfigImportScaffolder $scaffolder,
        PelicanFileService $files,
    ): JsonResponse {
        $data = $request->validated();
        $path = trim((string) $data['path']);

        $explicitFormat = $data['format'] ?? null;
        $format = $explicitFormat ?? ConfigImportScaffolder::detectFormat($path);
        if ($format === null || ! $parsers->has($format)) {
            return $this->error('unsupported_format', __('easy-configuration::messages.import.unsupported_format'));
        }

        $server = Server::query()->findOrFail($data['server_id']);
        if ($server->identifier === null || $server->identifier === '') {
            return $this->error('server_unavailable', __('easy-configuration::messages.import.server_unavailable'));
        }

        try {
            $raw = $files->getFileContent($server->identifier, $path);
        } catch (Throwable $e) {
            report($e);

            return $this->error('read_failed', __('easy-configuration::messages.import.read_failed'));
        }

        // Auto-upgrade a generic-XML auto-detection to the property-list format
        // when the file clearly uses `<property name= value=>` (e.g. 7DTD). An
        // explicit admin choice is always respected.
        if ($explicitFormat === null && $format === 'xml' && ConfigImportScaffolder::looksLikePropertyXml($raw)) {
            $format = 'xml-property';
        }

        $parsed = $parsers->get($format)->parse($raw);
        $file = $scaffolder->scaffold($path, $format, $parsed);

        return response()->json(['data' => [
            'file' => $file,
            'parameter_count' => count($parsed->parameters),
        ]]);
    }

    private function error(string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], 422);
    }
}
