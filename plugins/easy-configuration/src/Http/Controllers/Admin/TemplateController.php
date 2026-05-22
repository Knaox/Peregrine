<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
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

        return response()->json(['data' => $this->registry->all()]);
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
        return $this->persist($request->validated()['template']);
    }

    public function update(SaveTemplateRequest $request, string $id): JsonResponse
    {
        return $this->persist($request->validated()['template'], $id);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->storage->delete($id);
        $this->registry->rebuild();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function import(ImportTemplateRequest $request): JsonResponse
    {
        $decoded = json_decode($request->validated()['content'], true);
        if (! is_array($decoded)) {
            return $this->invalid(['Invalid JSON document']);
        }

        return $this->persist($decoded);
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
        $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || $this->storage->path($templateId) === null) {
            return $this->invalid(['Unable to serialise template']);
        }

        $this->storage->write($templateId, $json);
        $this->registry->rebuild();

        return response()->json(['data' => ['id' => $templateId]]);
    }

    /**
     * @param  list<string>  $messages
     */
    private function invalid(array $messages): JsonResponse
    {
        return response()->json(['error' => ['code' => 'invalid_template', 'messages' => $messages]], 422);
    }
}
