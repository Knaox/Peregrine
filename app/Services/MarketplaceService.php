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
     *
     * Each step throws on failure with an actionable message — the previous
     * version of this method silently swallowed errors (e.g. moveDirectory
     * returning false on a permission denial), so the controller showed
     * "Plugin installed" while the files never actually landed on disk.
     *
     * Bundled plugins (`"bundled": true` in plugin.json) cannot be installed
     * via this path — they ship with the panel itself, are tracked in the
     * Peregrine git repo, and should be (re)synced via `plugin:force-resync`
     * after a `git pull`. Trying to download + extract a bundled plugin would
     * either clash with the existing on-disk files (throw "already installed")
     * or, if the dir was first nuked, hand back a copy that git pull would
     * immediately overwrite again — pointless and confusing.
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

        $pluginsRoot = base_path('plugins');
        $pluginPath = base_path("plugins/{$pluginId}");

        // If the directory already exists, distinguish two cases :
        //   1. Bundled plugin (manifest says `bundled: true`) — managed by
        //      git, never re-installed via the marketplace. Direct the
        //      admin to the resync path.
        //   2. Random leftover dir from a half-rolled-back install. Tell
        //      the admin to clean up manually rather than silently nuking.
        if ($this->files->isDirectory($pluginPath)) {
            if ($this->isBundled($pluginPath)) {
                throw new \RuntimeException(
                    "Plugin '{$pluginId}' is bundled with Peregrine and cannot be reinstalled via the marketplace. "
                    ."Pull the latest panel source (`git pull`) then run `php artisan plugin:force-resync {$pluginId}` "
                    ."to sync the DB row + run any new migrations."
                );
            }
            throw new \RuntimeException(
                "Plugin '{$pluginId}' is already installed on disk at {$pluginPath}. "
                ."If you meant to update, use the 'Update' action. If the directory is a stale leftover, "
                ."remove it manually before retrying the install (the marketplace refuses to overwrite "
                ."unknown content as a safety measure)."
            );
        }

        // Sanity check : the plugins/ directory must exist and be writable
        // by the PHP-FPM user. Docker images that mount plugins/ as a
        // read-only volume (or with the wrong owner) silently failed every
        // step below — the symptom being "install reported success but the
        // plugin never appeared". Catch it up-front with a clear error.
        if (! $this->files->isDirectory($pluginsRoot)) {
            throw new \RuntimeException(
                "plugins/ directory does not exist at {$pluginsRoot}. Check your Peregrine install."
            );
        }
        if (! is_writable($pluginsRoot)) {
            throw new \RuntimeException(
                "plugins/ directory is not writable by the web server user. "
                ."In Docker : ensure the volume is mounted rw and owned by www-data "
                ."(typically `chown -R www-data:www-data plugins/` inside the container)."
            );
        }

        // Download ZIP
        $response = Http::timeout(30)->get($downloadUrl);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Failed to download plugin '{$pluginId}' (HTTP {$response->status()}). URL : {$downloadUrl}"
            );
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
        // Pre-clean a stale extraction from an aborted previous attempt.
        if ($this->files->isDirectory($tempDir)) {
            $this->files->deleteDirectory($tempDir);
        }
        $extractOk = $zip->extractTo($tempDir);
        $zip->close();
        $this->files->delete($tempZip);
        if (! $extractOk) {
            throw new \RuntimeException(
                "Failed to extract plugin archive '{$pluginId}' to {$tempDir}. "
                ."Likely cause : storage/app not writable by the web server user."
            );
        }

        // GitHub ZIPs contain a single root directory — find it
        $extractedDirs = $this->files->directories($tempDir);
        $sourceDir = count($extractedDirs) === 1 ? $extractedDirs[0] : $tempDir;

        // Place the extracted plugin under plugins/. We use copy + delete
        // rather than `moveDirectory` because the latter relies on PHP's
        // rename() which fails with EXDEV across filesystem boundaries —
        // very common in Docker, where admins typically mount plugins/ as
        // a separate volume to persist installs across container rebuilds.
        // copyDirectory traverses + writes file by file so it works across
        // any mount.
        $copied = $this->files->copyDirectory($sourceDir, $pluginPath);
        $this->files->deleteDirectory($tempDir);

        if (! $copied || ! $this->files->isDirectory($pluginPath)) {
            throw new \RuntimeException(
                "Failed to copy extracted plugin to {$pluginPath}. "
                ."Check plugins/ is writable by the web server user "
                ."(in Docker : `chown -R www-data:www-data plugins/` inside the container)."
            );
        }

        // Verify the plugin's manifest is readable post-move — catches the
        // case where the files moved but with the wrong permissions, so
        // PluginManager::discover() can't read them.
        $manifestPath = $pluginPath . '/plugin.json';
        if (! $this->files->exists($manifestPath) || ! is_readable($manifestPath)) {
            throw new \RuntimeException(
                "Plugin '{$pluginId}' moved to {$pluginPath} but plugin.json is missing or unreadable. "
                ."Check the file permissions inside the moved directory."
            );
        }

        // Sync with DB.
        //
        // Critical : preserve `is_active` AND `settings` AND `installed_at`
        // when the row already exists. Earlier versions of this method
        // hard-coded `is_active => false` in the updateOrCreate payload,
        // which silently DEACTIVATED any plugin that the admin had
        // activated, every time they ran an update from the marketplace.
        // Symptoms reported 2026-05-08 : "after marketplace update I have
        // to manually reactivate the plugin AND re-click save on its
        // settings before it actually works".
        //
        // Now : we look up the existing row first. If found, we only
        // bump `version` + `installed_at` ; `is_active` and the JSON
        // `settings` column are left untouched. Fresh installs still
        // start `is_active=false` so the admin opts in explicitly.
        $existing = \App\Models\Plugin::where('plugin_id', $pluginId)->first();

        if ($existing !== null) {
            $existing->update([
                'version' => $entry['version'] ?? '0.0.0',
                'installed_at' => now(),
            ]);
        } else {
            \App\Models\Plugin::create([
                'plugin_id' => $pluginId,
                'is_active' => false,
                'version' => $entry['version'] ?? '0.0.0',
                'installed_at' => now(),
            ]);
        }

        // Invalidate every cached singleton the plugin may have built up
        // before the update. The previous code only cleared the
        // marketplace registry cache (CACHE_KEY) — the plugin's OWN
        // caches (provider listings, settings singletons, manifest
        // enrichers, …) stayed pinned to the old version's data,
        // forcing the admin to "click save" on the settings page just
        // to trigger the model observer that flushes them. With a
        // global flush after each install/update, the new code reads
        // fresh data from disk + DB on the very next request.
        Cache::forget(self::CACHE_KEY);
        Cache::flush();
    }

    /**
     * Update a plugin to the latest version from the registry.
     *
     * Two paths :
     *
     *   1. Bundled plugin — source is tracked in the Peregrine git repo.
     *      The on-disk version moves with `git pull`, not with a download.
     *      "Update" here just bumps the DB version row to whatever's on
     *      disk and runs any new migrations (delegates to
     *      `PluginManager::forceResync()`).
     *
     *   2. Standard marketplace plugin — wipe the dir, re-download and
     *      re-extract from the registry. Each step is verified : if the
     *      delete fails (typical Docker permission mismatch), we throw
     *      with an actionable message instead of falling through to
     *      `install()` which would just say "already installed" again.
     */
    public function update(string $pluginId): void
    {
        $pluginPath = base_path("plugins/{$pluginId}");

        // No more bundled fast-path. Until 2026-05-08 this method had a
        // dedicated branch for `bundled: true` plugins that skipped the
        // ZIP download entirely and just resynced the DB row with
        // whatever was on disk — meaning a click on the "Update" button
        // for `invitations` (the bundled plugin) was a no-op : the
        // marketplace registry's new version was written into the
        // `plugins.version` column, but the actual files on disk + the
        // compiled JS bundle stayed at whatever the Docker image
        // shipped. The admin saw "Upgraded to latest" while running
        // OLD plugin code (broken i18n keys, missing routes, etc.).
        //
        // Fix : every plugin — bundled or not — now follows the same
        // path : delete the existing dir, re-download from the registry
        // ZIP, extract over the now-empty path, run migrations. Bundled
        // is now purely about WHAT SHIPS WITH FRESH PEREGRINE INSTALLS,
        // not about update flow.
        if ($this->files->isDirectory($pluginPath)) {
            $deleted = $this->files->deleteDirectory($pluginPath);
            if (! $deleted || $this->files->isDirectory($pluginPath)) {
                throw new \RuntimeException(
                    "Failed to remove the existing plugin directory at {$pluginPath} before updating. "
                    ."Likely cause : the directory contains files owned by a different user "
                    ."(e.g. a previous install ran as root or www-data, the current PHP process can't delete them). "
                    ."Fix : `chown -R \$(id -un):\$(id -gn) {$pluginPath}` then retry, "
                    ."or remove the directory manually before clicking Update again."
                );
            }
        }

        // Re-install : downloads + extracts + writes DB row preserving
        // is_active + settings (see install() since 2026-05-08). Will
        // re-throw if any step fails ; the dir is already gone at this
        // point so a half-failure leaves the plugin uninstalled, which
        // the admin can recover by clicking Install again.
        $this->install($pluginId);

        // Run any new migrations the upgraded version shipped + recreate
        // the public symlink (Docker redeploys nuke /public/plugins/).
        // forceResync preserves is_active so the plugin keeps booting if
        // the admin had it activated before the update. Without this
        // call, migration files newly added in the upgraded version
        // would only run on the next Activate cycle — leaving the
        // plugin running against a stale schema until then.
        $this->pluginManager->forceResync($pluginId);
    }

    /**
     * Read the manifest's `bundled` flag. Bundled plugins ship with the
     * panel source and must never be touched by the marketplace install /
     * update flow. Returns false defensively if the manifest is missing or
     * malformed (treat unknown plugins as "regular marketplace" so the
     * existing flow applies).
     */
    private function isBundled(string $pluginPath): bool
    {
        $manifestPath = $pluginPath . '/plugin.json';
        if (! $this->files->exists($manifestPath)) {
            return false;
        }
        try {
            $manifest = json_decode($this->files->get($manifestPath), true);
        } catch (\Throwable) {
            return false;
        }
        return is_array($manifest) && ! empty($manifest['bundled']);
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
