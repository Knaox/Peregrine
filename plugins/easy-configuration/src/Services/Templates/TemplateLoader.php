<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Templates;

/**
 * Loads template JSON files from storage and validates them, returning typed
 * outcomes. Pure (no DB) so the read/validate path can be unit-tested against a
 * temp directory; the registry layers the DB cache on top.
 */
final class TemplateLoader
{
    public function __construct(
        private readonly TemplateStorage $storage,
        private readonly TemplateSchemaValidator $validator,
    ) {}

    /** @return list<LoadedTemplate> */
    public function loadAll(): array
    {
        $out = [];
        foreach ($this->storage->list() as $path) {
            $id = $this->storage->idFromPath($path);
            $raw = $this->storage->read($id) ?? '';
            $out[] = $this->loadRaw($id, $raw, $path);
        }

        return $out;
    }

    public function loadOne(string $id): ?LoadedTemplate
    {
        $raw = $this->storage->read($id);
        if ($raw === null) {
            return null;
        }

        return $this->loadRaw($id, $raw, $this->storage->path($id) ?? $id);
    }

    private function loadRaw(string $id, string $raw, string $path): LoadedTemplate
    {
        $checksum = sha1($raw);

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return LoadedTemplate::invalid($id, $path, $checksum, 'Invalid JSON document');
        }

        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            return LoadedTemplate::invalid($id, $path, $checksum, implode('; ', $errors));
        }

        return LoadedTemplate::valid($id, $path, $checksum, TemplateDefinition::fromArray($data));
    }
}
