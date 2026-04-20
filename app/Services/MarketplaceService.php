<?php

namespace App\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MarketplaceService
{
    private const CACHE_KEY = 'marketplace.registry';

    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly PluginManager $pluginManager,
        private readonly Filesystem $files,
    ) {}

    /**
     * Fetch the plugin registry from GitHub (cached 1h).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchRegistry(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $url = config('panel.marketplace.registry_url');

            if (! $url) {
                return [];
            }

            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            return $data['plugins'] ?? [];
        });
    }

    /**
     * Every plugin listed in the marketplace, annotated with local state
     * (is_installed + installed_version + update_available). Returned even
     * for plugins shipped by default, so the admin can see the canonical
     * version from the registry.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listWithStatus(): array
    {
        $registry = $this->fetchRegistry();
        $discovered = $this->pluginManager->discover();
        $dbPlugins = \App\Models\Plugin::all()->keyBy('plugin_id');
        $result = [];

        foreach ($registry as $entry) {
            $id = $entry['id'] ?? null;

            if (! $id) {
                continue;
            }

            $local = $discovered[$id] ?? null;
            $dbRecord = $dbPlugins->get($id);
            $installedVersion = $dbRecord?->version ?? ($local['version'] ?? null);
            $registryVersion = $entry['version'] ?? null;

            $entry['is_installed'] = $local !== null;
            $entry['installed_version'] = $installedVersion;
            $entry['update_available'] = $installedVersion !== null
                && $registryVersion !== null
                && version_compare((string) $registryVersion, (string) $installedVersion, '>');

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Back-compat wrapper: only entries that are NOT yet on disk.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailable(): array
    {
        return array_values(array_filter(
            $this->listWithStatus(),
            static fn (array $e): bool => empty($e['is_installed']),
        ));
    }

    /**
     * Drop the cached registry so the next fetch pulls from GitHub.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Install a plugin from the marketplace.
     */
    public function install(string $pluginId): void
    {
        $registry = $this->fetchRegistry();
        $entry = null;

        foreach ($registry as $item) {
            if (($item['id'] ?? null) === $pluginId) {
                $entry = $item;

                break;
            }
        }

        if (! $entry) {
            throw new \RuntimeException("Plugin '{$pluginId}' not found in the marketplace registry.");
        }

        $downloadUrl = $entry['download_url'] ?? null;

        if (! $downloadUrl) {
            throw new \RuntimeException("No download URL for plugin '{$pluginId}'.");
        }

        $pluginPath = base_path("plugins/{$pluginId}");

        if ($this->files->isDirectory($pluginPath)) {
            throw new \RuntimeException("Plugin '{$pluginId}' is already installed on disk.");
        }

        // Download ZIP
        $response = Http::timeout(30)->get($downloadUrl);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to download plugin '{$pluginId}'.");
        }

        // Save to temp file
        $tempZip = storage_path("app/plugin-{$pluginId}.zip");
        $this->files->put($tempZip, $response->body());

        // Extract
        $zip = new \ZipArchive;

        if ($zip->open($tempZip) !== true) {
            $this->files->delete($tempZip);

            throw new \RuntimeException("Failed to open plugin archive for '{$pluginId}'.");
        }

        $tempDir = storage_path("app/plugin-{$pluginId}-extract");
        $zip->extractTo($tempDir);
        $zip->close();
        $this->files->delete($tempZip);

        // GitHub ZIPs contain a single root directory — find it
        $extractedDirs = $this->files->directories($tempDir);
        $sourceDir = count($extractedDirs) === 1 ? $extractedDirs[0] : $tempDir;

        // Move to plugins/
        $this->files->moveDirectory($sourceDir, $pluginPath);
        $this->files->deleteDirectory($tempDir);

        // Sync with DB
        \App\Models\Plugin::updateOrCreate(
            ['plugin_id' => $pluginId],
            [
                'is_active' => false,
                'version' => $entry['version'] ?? '0.0.0',
                'installed_at' => now(),
            ],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Update a plugin to the latest version from the registry.
     */
    public function update(string $pluginId): void
    {
        $pluginPath = base_path("plugins/{$pluginId}");

        // Remove old version
        if ($this->files->isDirectory($pluginPath)) {
            $this->files->deleteDirectory($pluginPath);
        }

        // Re-install
        $this->install($pluginId);
    }

    /**
     * Check which installed plugins have updates available.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkUpdates(): array
    {
        $registry = $this->fetchRegistry();
        $discovered = $this->pluginManager->discover();
        $updates = [];

        foreach ($registry as $entry) {
            $id = $entry['id'] ?? null;

            if (! $id || ! isset($discovered[$id])) {
                continue;
            }

            $localVersion = $discovered[$id]['version'] ?? '0.0.0';
            $registryVersion = $entry['version'] ?? '0.0.0';

            if (version_compare($registryVersion, $localVersion, '>')) {
                $updates[] = [
                    'id' => $id,
                    'name' => $entry['name'] ?? $id,
                    'local_version' => $localVersion,
                    'latest_version' => $registryVersion,
                ];
            }
        }

        return $updates;
    }
}
