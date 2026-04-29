<?php

namespace Plugins\EggConfigEditor;

use App\Models\Plugin;
use App\Services\Plugin\ManifestEnricherRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\EggConfigEditor\Filament\Resources\EggConfigFileResource;
use Plugins\EggConfigEditor\Models\EggConfigFile;

/**
 * Egg Config Editor — bootstraps the plugin once it's activated by the admin.
 *
 *  - Loads its own migrations (`src/Migrations/`).
 *  - Registers the `/api/plugins/egg-config-editor/*` routes the React
 *    bundle calls.
 *  - When the `invitations` plugin is active, registers two dedicated
 *    permissions (`eggconfig.read` / `eggconfig.write`) into the shared
 *    `PermissionRegistry` so admins can grant config-editor access to
 *    invited subusers without giving them generic `file.*` rights.
 *  - Registers a manifest enricher so `/api/plugins` reports the eggs that
 *    actually have a config file declared in DB. The frontend uses
 *    `requires_egg_ids` on the home section to skip the card entirely on
 *    irrelevant servers (no mount, no API call, no flash of empty state).
 *  - Best-effort fallback registration of the Filament resource (only
 *    matters when running against an older Peregrine core that doesn't
 *    yet have `PluginManager::contributeToFilamentPanel()`). The current
 *    core auto-discovers Filament resources from active plugins at panel
 *    construction time, so this fallback is a no-op for fresh installs.
 */
class EggConfigEditorServiceProvider extends ServiceProvider
{
    /**
     * Cache key for the dynamically-computed list of eggs that have at
     * least one enabled config file declared. Busted by the Eloquent
     * observer below whenever an EggConfigFile row changes.
     */
    public const APPLICABLE_EGGS_CACHE_KEY = 'plugin.egg-config-editor.applicable_eggs';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');

        Route::prefix('api/plugins/egg-config-editor')
            ->middleware('api')
            ->group(__DIR__ . '/Routes/api.php');

        $this->registerSubuserPermissions();
        $this->registerManifestEnricher();
        $this->registerEggConfigFileObserver();

        // Backward-compat fallback for Peregrine cores < the one that
        // ships `contributeToFilamentPanel()`. The new core handles the
        // registration itself at panel construction time (so routes are
        // built including this resource); on older cores this `booted`
        // callback at least adds the resource to the panel's list — useful
        // for nav rendering even if the route may not exist.
        if (class_exists(Filament::class)) {
            $this->app->booted(function (): void {
                try {
                    Filament::getDefaultPanel()->resources([
                        EggConfigFileResource::class,
                    ]);
                } catch (\Throwable) {
                    // No default panel registered yet — nothing to do.
                }
            });
        }
    }

    /**
     * Register the `eggconfig.read` / `eggconfig.write` permissions in the
     * Invitations plugin's PermissionRegistry — but only when Invitations
     * is itself active. Without it, no subuser system exists, so the
     * permissions would be dead entries.
     *
     * Detection is a single DB query, cached by Laravel's connection-level
     * statement cache. Catches Throwable defensively so a missing
     * `plugins` table during fresh setup never blocks boot.
     */
    private function registerSubuserPermissions(): void
    {
        try {
            $invitationsActive = Plugin::query()
                ->where('plugin_id', 'invitations')
                ->where('is_active', true)
                ->exists();
        } catch (\Throwable) {
            return;
        }

        if (! $invitationsActive) {
            return;
        }

        $registryClass = '\\Plugins\\Invitations\\Services\\PermissionRegistry';

        if (! class_exists($registryClass)) {
            return;
        }

        $registryClass::getInstance()->registerGroup(
            groupKey: 'eggconfig',
            groupLabel: [
                'en' => 'Config editor',
                'fr' => 'Éditeur de config',
            ],
            permissions: [
                'eggconfig.read' => [
                    'en' => 'View game configs',
                    'fr' => 'Consulter les configs de jeu',
                ],
                'eggconfig.write' => [
                    'en' => 'Edit game configs',
                    'fr' => 'Modifier les configs de jeu',
                ],
            ],
        );
    }

    /**
     * Inject `requires_egg_ids` into the plugin's `server_home_sections`
     * entry so the frontend hides the card on servers whose egg has no
     * config file declared. The list is cached for 60s — busted by the
     * Eloquent observer when an EggConfigFile is created/updated/deleted.
     */
    private function registerManifestEnricher(): void
    {
        ManifestEnricherRegistry::getInstance()->register('egg-config-editor', function (array $manifest): array {
            $eggIds = Cache::remember(self::APPLICABLE_EGGS_CACHE_KEY, 60, function (): array {
                try {
                    return EggConfigFile::query()
                        ->where('enabled', true)
                        ->pluck('egg_ids')
                        ->flatten()
                        ->filter(fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                } catch (\Throwable) {
                    return [];
                }
            });

            $sections = $manifest['server_home_sections'] ?? [];
            foreach ($sections as $idx => $section) {
                if (($section['id'] ?? null) === 'egg-config-editor') {
                    $sections[$idx]['requires_egg_ids'] = $eggIds;
                }
            }
            $manifest['server_home_sections'] = $sections;

            return $manifest;
        });
    }

    /**
     * Bust the applicable-eggs cache whenever an EggConfigFile changes so
     * the frontend reflects the new egg list within one round-trip — no
     * stale 60s window after an admin edit.
     */
    private function registerEggConfigFileObserver(): void
    {
        $bust = function (): void {
            Cache::forget(self::APPLICABLE_EGGS_CACHE_KEY);
        };

        EggConfigFile::created($bust);
        EggConfigFile::updated($bust);
        EggConfigFile::deleted($bust);
    }
}
