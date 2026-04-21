<?php

namespace App\Services\Plugin;

use Illuminate\Filesystem\Filesystem;

/**
 * Pure filesystem ops — scan `plugins/` for `plugin.json` manifests
 * and resolve plugin paths. No DB, no cache, no side-effects.
 */
class PluginDiscovery
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * Discover all plugins by scanning the plugins/ directory.
     *
     * @return array<string, array<string, mixed>>
     */
    public function discover(): array
    {
        $plugins = [];
        $basePath = $this->pluginsBasePath();

        if (! $this->files->isDirectory($basePath)) {
            return $plugins;
        }

        foreach ($this->files->directories($basePath) as $dir) {
            $manifestPath = $dir . '/plugin.json';

            if (! $this->files->exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode($this->files->get($manifestPath), true);

            if (! is_array($manifest) || empty($manifest['id'])) {
                continue;
            }

            $plugins[$manifest['id']] = $manifest;
        }

        return $plugins;
    }

    /**
     * Read and parse a plugin's manifest.
     *
     * @return array<string, mixed>|null
     */
    public function getManifest(string $pluginId): ?array
    {
        $path = $this->getPluginPath($pluginId);

        if (! $path) {
            return null;
        }

        $manifestPath = $path . '/plugin.json';

        if (! $this->files->exists($manifestPath)) {
            return null;
        }

        $data = json_decode($this->files->get($manifestPath), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Resolve the filesystem path of a plugin.
     */
    public function getPluginPath(string $pluginId): ?string
    {
        $path = $this->pluginsBasePath() . '/' . $pluginId;

        return $this->files->isDirectory($path) ? $path : null;
    }

    public function pluginsBasePath(): string
    {
        return base_path('plugins');
    }
}
