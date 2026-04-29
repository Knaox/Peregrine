<?php

namespace App\Services\Plugin;

use Closure;

/**
 * Process-wide registry of "manifest enrichers" — closures that mutate a
 * plugin's manifest right before it's served to the frontend by
 * `PluginBootstrap::getActiveManifests()`.
 *
 * Why a singleton instead of a container binding : plugins boot dynamically
 * (their ServiceProvider only registers when DB-active) and Laravel's
 * container scope is shared between requests. A static singleton matches
 * the same pattern used by `Plugins\Invitations\Services\PermissionRegistry`.
 *
 * Typical use case : a plugin needs the frontend to see DB-derived state
 * inside its manifest (e.g. egg-config-editor wants `requires_egg_ids` on
 * its `server_home_sections` to reflect the eggs actually configured in DB).
 * Hard-coding in `plugin.json` doesn't work because the value changes
 * whenever the admin adds/removes a config row.
 *
 * Usage (from a plugin's ServiceProvider::boot()):
 *
 *   ManifestEnricherRegistry::getInstance()->register('my-plugin', function (array $m): array {
 *       $m['server_home_sections'][0]['requires_egg_ids'] = MyModel::pluck('egg_id')->all();
 *       return $m;
 *   });
 *
 * The returned array replaces the manifest. The closure receives a fully-
 * assembled manifest (with `server_home_sections`, `settings`, `bundle_url`
 * already populated) and must return the same shape.
 */
class ManifestEnricherRegistry
{
    private static ?self $instance = null;

    /** @var array<string, Closure> Map of plugin_id => enricher closure. */
    private array $enrichers = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Register an enricher for the given plugin id. Replaces any previously
     * registered enricher for the same id (last writer wins — a plugin
     * shouldn't register more than one anyway).
     */
    public function register(string $pluginId, Closure $enricher): void
    {
        $this->enrichers[$pluginId] = $enricher;
    }

    /**
     * Apply the registered enricher (if any) to the given manifest. Returns
     * the manifest unchanged when no enricher is registered, or when the
     * enricher throws — we never want a buggy enricher to take down the
     * whole `/api/plugins` endpoint.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function apply(string $pluginId, array $manifest): array
    {
        if (! isset($this->enrichers[$pluginId])) {
            return $manifest;
        }

        try {
            $result = ($this->enrichers[$pluginId])($manifest);
            return is_array($result) ? $result : $manifest;
        } catch (\Throwable) {
            return $manifest;
        }
    }

    /**
     * Reset the registry. Test-only — allows test cases to start from a
     * clean slate without leaking state across runs.
     */
    public function reset(): void
    {
        $this->enrichers = [];
    }
}
