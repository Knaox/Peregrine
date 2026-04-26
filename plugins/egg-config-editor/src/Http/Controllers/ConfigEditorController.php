<?php

namespace Plugins\EggConfigEditor\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Plugins\EggConfigEditor\Models\EggConfigFile;
use Plugins\EggConfigEditor\Services\ConfigParserService;

/**
 * Player-facing endpoints for the egg config editor (v0.2 — auto-detection
 * driven by the plugin's i18n dictionary).
 *
 *   GET  /api/plugins/egg-config-editor/servers/{id}/configs
 *        → list of config files exposed for this server's egg
 *
 *   GET  /api/plugins/egg-config-editor/servers/{id}/configs/{configId}
 *        → reads + parses the file, returns RAW key/value/inferred-type list.
 *          Labels, constraints, hidden flags are applied frontend-side
 *          against the plugin's i18n dictionary (`params.<key>.label`, etc.)
 *
 *   POST /api/plugins/egg-config-editor/servers/{id}/configs/{configId}
 *        → writes back the values the player submitted, preserving comments
 *          and any non-listed lines via the parser's round-trip behaviour.
 *
 * Permission model : Pelican file manager parity. `file.read` to view,
 * `file.update` to save. Owners always pass.
 */
class ConfigEditorController extends Controller
{
    public function __construct(
        private readonly ConfigParserService $parser,
        private readonly PelicanFileService $fileService,
    ) {}

    public function listConfigs(int $serverId, Request $request): JsonResponse
    {
        $server = $this->resolveServer($serverId, $request);
        $this->authorizeRead($server, $request);

        $configs = EggConfigFile::query()
            ->forEgg((int) $server->egg_id)
            ->where('enabled', true)
            ->orderBy('id')
            ->get()
            ->map(function (EggConfigFile $f): array {
                $paths = $f->file_paths ?? [];
                // Display the first path as the canonical one — the actual
                // path used at read time is resolved server-side in
                // readConfig (it picks the first path that exists). The
                // frontend only needs a stable label for the picker.
                $primary = $paths[0] ?? '';
                return [
                    'id' => $f->id,
                    'file_paths' => $paths,
                    'file_type' => $f->file_type,
                    'default_label' => $primary !== '' ? basename($primary) : '(unnamed)',
                ];
            });

        return response()->json(['data' => $configs]);
    }

    public function readConfig(int $serverId, int $configId, Request $request): JsonResponse
    {
        $server = $this->resolveServer($serverId, $request);
        $this->authorizeRead($server, $request);
        $config = $this->resolveConfig($configId, $server->egg_id);

        // Try each declared path in order, use the first that exists. This
        // covers multi-OS games (ARK ships either under LinuxServer/ or
        // WindowsServer/) without forcing the admin to commit to one.
        $resolution = $this->resolveFileContent($server->identifier, $config->file_paths ?? []);
        $resolvedPath = $resolution['path'];
        $fileExists = $resolution['exists'];
        $parsed = [];
        if ($fileExists && $resolution['content'] !== null) {
            try {
                $parsed = $this->parser->parse($resolution['content'], $config->file_type);
            } catch (\Throwable $e) {
                Log::warning('ConfigEditor: failed to parse file', [
                    'server_id' => $server->id,
                    'file_path' => $resolvedPath,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Each parameter is reported with the RAW key + value + an inferred
        // type + the INI section it came from (parsed from the dotted key
        // notation `Section.Key` the parser emits). Properties / JSON files
        // never carry sections — `section` stays null and the frontend
        // renders a single "General" group.
        //
        // When the admin set a sections whitelist, every other section is
        // dropped here. The parser still preserves untouched sections in
        // the file on save (preserve-unknown-lines), so the player can't
        // accidentally wipe `[ScalabilityGroups]` etc. by saving.
        $sectionsWhitelist = $this->normalizeSectionsWhitelist($config->sections, $config->file_type);
        $parameters = [];
        foreach ($parsed as $key => $value) {
            $section = $this->extractSection((string) $key, $config->file_type);
            if ($sectionsWhitelist !== null && ($section === null || ! in_array($section, $sectionsWhitelist, true))) {
                continue;
            }
            $parameters[] = [
                'config_key' => (string) $key,
                'value' => $value,
                'inferred_type' => $this->inferType($value),
                'section' => $section,
            ];
        }

        return response()->json([
            'data' => [
                'id' => $config->id,
                // The actual path used at read time (or the first declared
                // path when none exists yet — useful in the "file_missing"
                // hint shown to the player).
                'file_path' => $resolvedPath,
                'file_type' => $config->file_type,
                'file_exists' => $fileExists,
                'parameters' => $parameters,
            ],
        ]);
    }

    public function saveConfig(int $serverId, int $configId, Request $request): JsonResponse
    {
        $server = $this->resolveServer($serverId, $request);
        $this->authorizeWrite($server, $request);
        $config = $this->resolveConfig($configId, $server->egg_id);

        $validated = $request->validate([
            'values' => ['required', 'array'],
            'values.*' => ['present', 'nullable'],
        ]);

        if ($validated['values'] === []) {
            return response()->json(['error' => 'no_values'], 422);
        }

        // Read the existing file when present so comments + non-listed lines
        // are preserved through the round trip. The same multi-path
        // resolution as readConfig — write back to whichever variant
        // currently exists. When none exist yet, seed an empty original and
        // write to the FIRST declared path (the admin's preferred default).
        $resolution = $this->resolveFileContent($server->identifier, $config->file_paths ?? []);
        $writePath = $resolution['path'];
        if ($writePath === '') {
            return response()->json(['error' => 'no_file_paths_configured'], 422);
        }
        $original = $resolution['exists'] && $resolution['content'] !== null
            ? $resolution['content']
            : '';

        // Coerce each submitted value to the right scalar type so the parser
        // serializes it cleanly (booleans stay booleans, numbers stay
        // numeric strings without `.0` artifacts).
        $coerced = [];
        foreach ($validated['values'] as $key => $value) {
            $coerced[(string) $key] = $this->coerceForFile($value);
        }

        $merged = array_merge(
            $this->parser->parse($original, $config->file_type),
            $coerced,
        );
        $serialized = $this->parser->serialize($merged, $config->file_type, $original);

        $this->fileService->writeFile($server->identifier, $writePath, $serialized);

        return response()->json([
            'message' => 'saved',
            'updated_keys' => array_keys($coerced),
            'file_path' => $writePath,
        ]);
    }

    /**
     * Try each candidate path in order and return the first one that
     * resolves successfully. When none exist, returns the first declared
     * path with `exists=false` so callers can show "the file doesn't exist
     * yet" hints / write to a sensible default location.
     *
     * @param  array<int, string>  $paths
     * @return array{path: string, exists: bool, content: ?string}
     */
    private function resolveFileContent(string $serverIdentifier, array $paths): array
    {
        if ($paths === []) {
            return ['path' => '', 'exists' => false, 'content' => null];
        }

        foreach ($paths as $candidate) {
            $candidate = (string) $candidate;
            if ($candidate === '') {
                continue;
            }
            try {
                $content = $this->fileService->getFileContent($serverIdentifier, $candidate);
                return ['path' => $candidate, 'exists' => true, 'content' => $content];
            } catch (RequestException $e) {
                $body = (string) $e->response?->body();
                if (str_contains($body, 'FileNotFoundException')) {
                    continue; // try the next candidate
                }
                // Anything else (network, 401, permissions) is a real error.
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('ConfigEditor: unexpected error trying path, skipping', [
                    'path' => $candidate,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // None of the candidates exist — return the first as the write
        // target so a save creates the file at the admin's preferred path.
        return ['path' => (string) $paths[0], 'exists' => false, 'content' => null];
    }

    // -- internals -------------------------------------------------------

    private function resolveServer(int $serverId, Request $request): Server
    {
        return Server::query()
            ->where('id', $serverId)
            ->accessibleBy($request->user())
            ->firstOrFail();
    }

    private function resolveConfig(int $configId, ?int $eggId): EggConfigFile
    {
        $config = EggConfigFile::query()
            ->where('id', $configId)
            ->where('enabled', true)
            ->firstOrFail();

        // Defensive: guarantee the file row covers this server's egg so a
        // crafty player can't read another egg's config by guessing the id.
        if ($eggId !== null && ! in_array($eggId, $config->egg_ids ?? [], true)) {
            abort(404);
        }

        return $config;
    }

    private function authorizeRead(Server $server, Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($this->isOwner($server, $user)) {
            return;
        }
        if (! $user->hasServerPermission($server, 'file.read')) {
            abort(403);
        }
    }

    private function authorizeWrite(Server $server, Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($this->isOwner($server, $user)) {
            return;
        }
        if (! $user->hasServerPermission($server, 'file.update')) {
            abort(403);
        }
    }

    private function isOwner(Server $server, mixed $user): bool
    {
        return $server->user_id === $user->id;
    }

    /**
     * Extract the INI section name from a parser-emitted key. The parser
     * uses ASCII Unit Separator (`\x1F`) so we can disambiguate even when
     * the section name itself contains dots (e.g. Unreal Engine's
     * `[/script/shootergame.shootergamemode]`). Non-INI files have no
     * section.
     */
    private function extractSection(string $configKey, string $fileType): ?string
    {
        if ($fileType !== 'ini') {
            return null;
        }
        $sep = ConfigParserService::SECTION_KEY_SEPARATOR;
        $pos = strpos($configKey, $sep);
        if ($pos === false) {
            return null;
        }
        $section = substr($configKey, 0, $pos);
        return $section !== '' ? $section : null;
    }

    /**
     * Normalize the admin-provided sections whitelist. Returns null when no
     * filtering should happen (empty list or non-INI file). Filters out
     * empty / non-string entries defensively.
     *
     * @param  array<int, mixed>|null  $rawSections
     * @return array<int, string>|null
     */
    private function normalizeSectionsWhitelist(?array $rawSections, string $fileType): ?array
    {
        if ($fileType !== 'ini') {
            return null;
        }
        if ($rawSections === null || $rawSections === []) {
            return null;
        }
        $clean = [];
        foreach ($rawSections as $entry) {
            if (! is_string($entry)) continue;
            $entry = trim($entry);
            if ($entry === '') continue;
            $clean[] = $entry;
        }
        return $clean === [] ? null : $clean;
    }

    /**
     * Best-guess type for a parsed scalar value. The frontend uses this only
     * as a fallback when the plugin's i18n dictionary doesn't ship a type
     * for the key.
     */
    private function inferType(mixed $value): string
    {
        $str = is_scalar($value) ? (string) $value : '';
        $lower = strtolower($str);
        if (in_array($lower, ['true', 'false'], true)) {
            return 'boolean';
        }
        if ($str !== '' && is_numeric($str)) {
            return 'number';
        }
        return 'text';
    }

    /**
     * Convert frontend-submitted values to a parser-friendly scalar.
     *
     * - JS booleans (`true` / `false`) round-trip through the JSON body as
     *   actual booleans → keep them.
     * - JS numbers come through as numbers → keep them.
     * - Strings are kept as-is (the parser itself decides quoting).
     */
    private function coerceForFile(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value) || is_numeric($value)) {
            return $value;
        }
        return (string) $value;
    }
}
