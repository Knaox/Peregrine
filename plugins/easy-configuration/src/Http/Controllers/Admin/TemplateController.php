<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Plugins\EasyConfiguration\Http\Requests\AddTemplateParameterRequest;
use Plugins\EasyConfiguration\Http\Requests\ImportTemplateRequest;
use Plugins\EasyConfiguration\Http\Requests\SaveTemplateRequest;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use Plugins\EasyConfiguration\Services\Templates\TemplateSchemaValidator;
use Plugins\EasyConfiguration\Services\Templates\TemplateStorage;

/**
 * Admin CRUD over the on-disk template JSON files (admin-gated by route
 * middleware). Disk is the source of truth; every mutation re-syncs the
 * `easy_config_templates` cache so listings and egg lookups stay current.
 */
final class TemplateController
{
    public function __construct(
        private readonly TemplateStorage $storage,
        private readonly TemplateRegistry $registry,
        private readonly TemplateSchemaValidator $validator,
    ) {}

    public function index(): JsonResponse
    {
        $this->registry->rebuild();

        // `has_egg` flags templates shipping a Pelican egg bundle so the list
        // can surface the "import egg into Pelican" action.
        $rows = $this->registry->all()->map(static function ($row): array {
            $payload = $row->toArray();
            $path = TemplateEggController::bundlePath((string) $row->template_id);
            $payload['has_egg'] = $path !== null && is_file($path);

            return $payload;
        });

        return response()->json(['data' => $rows]);
    }

    public function show(string $id): JsonResponse
    {
        $raw = $this->storage->read($id);
        if ($raw === null) {
            abort(404);
        }

        $definition = $this->registry->definition($id);

        return response()->json(['data' => [
            'id' => $id,
            'raw' => $raw,
            'valid' => $definition !== null,
            'definition' => $definition?->data,
        ]]);
    }

    public function store(SaveTemplateRequest $request): JsonResponse
    {
        // Use the raw `template` input, not validated(): the request only declares
        // rules for `template` + `template.id`, so validated() strips every other
        // key (version, name, target_eggs, files…). The full structure is then
        // validated by TemplateSchemaValidator in persist().
        return $this->persist((array) $request->input('template'));
    }

    public function update(SaveTemplateRequest $request, string $id): JsonResponse
    {
        return $this->persist((array) $request->input('template'), $id);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->storage->delete($id);
        $this->registry->rebuild();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Annotate a single parameter into an existing template (the inline
     * "promote a discovered key" flow). Merges the definition into the right
     * file's `parameters` (flat key, or section -> key for ini/toml), then reuses
     * persist() to schema-validate + write + rebuild. Upserts, so re-annotating
     * an existing key just updates it.
     */
    public function addParameter(AddTemplateParameterRequest $request, string $id): JsonResponse
    {
        $raw = $this->storage->read($id);
        if ($raw === null) {
            abort(404);
        }

        $template = json_decode($raw, true);
        if (! is_array($template)) {
            return $this->invalid(['Unable to read template']);
        }

        $data = $request->validated();
        $fileId = (string) $data['file_id'];
        $section = isset($data['section']) && $data['section'] !== '' ? (string) $data['section'] : null;
        $key = (string) $data['key'];

        $files = is_array($template['files'] ?? null) ? $template['files'] : [];
        $index = null;
        foreach ($files as $i => $file) {
            if (is_array($file) && (string) ($file['id'] ?? '') === $fileId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return $this->invalid(['Unknown file: '.$fileId]);
        }

        $params = is_array($files[$index]['parameters'] ?? null) ? $files[$index]['parameters'] : [];
        if ($section === null) {
            $params[$key] = $this->parameterDefinition($data);
        } else {
            $existing = is_array($params[$section] ?? null) ? $params[$section] : [];
            $existing[$key] = $this->parameterDefinition($data);
            $params[$section] = $existing;
        }
        $files[$index]['parameters'] = $params;
        $template['files'] = $files;

        return $this->persist($template, $id);
    }

    public function import(ImportTemplateRequest $request): JsonResponse
    {
        $decoded = json_decode($request->validated()['content'], true);
        if (! is_array($decoded)) {
            return $this->invalid(['Invalid JSON document']);
        }

        return $this->persist($decoded);
    }

    /**
     * Serve the bundled reference template (`samples/example-template.json`) so
     * the admin can open it in the editor — a complete, schema-valid starting
     * point. Read-only here; the admin saves it as a new template if they want.
     */
    public function example(): JsonResponse
    {
        $path = dirname(__DIR__, 4).'/samples/example-template.json';
        $raw = is_file($path) ? (string) file_get_contents($path) : '';
        $definition = $raw !== '' ? json_decode($raw, true) : null;
        if (! is_array($definition)) {
            abort(404);
        }

        return response()->json(['data' => ['definition' => $definition]]);
    }

    public function export(string $id): Response
    {
        $raw = $this->storage->read($id);
        if ($raw === null) {
            abort(404);
        }

        return response($raw, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$id}.json\"",
        ]);
    }

    /** @param array<string, mixed> $template */
    private function persist(array $template, ?string $id = null): JsonResponse
    {
        $errors = $this->validator->validate($template);
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $templateId = $id ?? (string) ($template['id'] ?? '');
        // JSON_INVALID_UTF8_SUBSTITUTE: a real game config (e.g. an ARK
        // GameUserSettings.ini) can carry non-UTF-8 bytes in a value; substitute
        // them instead of failing the whole save. The default is only a fallback
        // anyway — the live file value is what the player edits.
        $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || $this->storage->path($templateId) === null) {
            return $this->invalid(['Unable to serialise template']);
        }

        $this->storage->write($templateId, $json);
        $this->registry->rebuild();

        return response()->json(['data' => ['id' => $templateId]]);
    }

    /**
     * Build a template parameter definition from the validated payload, omitting
     * empty localised label/description/config so the stored JSON stays clean.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function parameterDefinition(array $data): array
    {
        $def = ['display_type' => (string) $data['display_type']];

        foreach (['label', 'description', 'config'] as $field) {
            if (isset($data[$field]) && is_array($data[$field]) && array_filter($data[$field], static fn ($v): bool => $v !== null && $v !== '') !== []) {
                $def[$field] = array_filter($data[$field], static fn ($v): bool => $v !== null && $v !== '');
            }
        }
        if (isset($data['env_var']) && $data['env_var'] !== '') {
            $def['env_var'] = (string) $data['env_var'];
        }

        return $def;
    }

    /**
     * @param  list<string>  $messages
     */
    private function invalid(array $messages): JsonResponse
    {
        return response()->json(['error' => ['code' => 'invalid_template', 'messages' => $messages]], 422);
    }
}
