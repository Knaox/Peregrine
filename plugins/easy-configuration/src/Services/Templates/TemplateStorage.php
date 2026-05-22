<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Templates;

use Illuminate\Filesystem\Filesystem;

/**
 * Reads/writes the on-disk template JSON files. The root is injected (defaults
 * to `storage/app/easy-config/templates` via the service provider) so tests can
 * point it at a temp dir. Template ids are slug-sanitised before they touch the
 * filesystem so a malicious id can't escape the templates directory.
 */
final class TemplateStorage
{
    public function __construct(
        private readonly string $root,
        private readonly Filesystem $files = new Filesystem,
    ) {}

    /** @return list<string> absolute paths of every *.json template file */
    public function list(): array
    {
        if (! $this->files->isDirectory($this->root)) {
            return [];
        }

        return array_values($this->files->glob($this->root.DIRECTORY_SEPARATOR.'*.json') ?: []);
    }

    public function read(string $id): ?string
    {
        $path = $this->path($id);
        if ($path === null || ! $this->files->isFile($path)) {
            return null;
        }

        $content = $this->files->get($path);

        return $content === '' ? null : $content;
    }

    public function write(string $id, string $json): void
    {
        $path = $this->path($id);
        if ($path === null) {
            return;
        }
        $this->files->ensureDirectoryExists($this->root);
        $this->files->put($path, $json);
    }

    public function delete(string $id): void
    {
        $path = $this->path($id);
        if ($path !== null && $this->files->isFile($path)) {
            $this->files->delete($path);
        }
    }

    public function exists(string $id): bool
    {
        $path = $this->path($id);

        return $path !== null && $this->files->isFile($path);
    }

    /** Sanitised absolute path for a template id, or null if the id is unsafe. */
    public function path(string $id): ?string
    {
        $safe = preg_replace('/[^a-z0-9._-]/i', '', $id) ?? '';
        $safe = trim($safe, '.');
        if ($safe === '') {
            return null;
        }

        return $this->root.DIRECTORY_SEPARATOR.$safe.'.json';
    }

    public function idFromPath(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }
}
