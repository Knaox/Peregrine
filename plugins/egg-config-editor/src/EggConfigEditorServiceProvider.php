<?php

namespace Plugins\EggConfigEditor;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\EggConfigEditor\Filament\Resources\EggConfigFileResource;

/**
 * Egg Config Editor — bootstraps the plugin once it's activated by the admin.
 *
 *  - Loads its own migrations (`src/Migrations/`) so the plugin's tables are
 *    auto-created when the admin enables it.
 *  - Registers the `/api/plugins/egg-config-editor/*` routes that the React
 *    bundle calls (list/read/save config files, auto-detect parameters,
 *    list and import presets).
 *  - Registers the Filament admin resource so the admin can configure which
 *    files / parameters are exposed per egg.
 *
 * The plugin is intentionally narrow : Pelican is the source of truth for the
 * actual file content; we just present a curated, validated form on top of it
 * via PelicanFileService.
 */
class EggConfigEditorServiceProvider extends ServiceProvider
{
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

        // Register the Filament resource on the default admin panel. Done in
        // a `booted()` callback so it runs AFTER Filament's PanelProvider has
        // declared the default panel — calling `getDefaultPanel()` from boot()
        // directly would race with Filament's own boot order.
        //
        // `Filament::serving()` would also work but only fires once per
        // request : if the page being rendered is not a Filament panel page
        // (e.g. an API call), the resource is never registered, and Filament's
        // route cache rebuild misses it. Registering at app boot is the
        // reliable path.
        if (class_exists(Filament::class)) {
            $this->app->booted(function (): void {
                try {
                    Filament::getDefaultPanel()->resources([
                        EggConfigFileResource::class,
                    ]);
                } catch (\Throwable) {
                    // No default panel registered yet (running in a non-panel
                    // context like artisan commands) — nothing to do.
                }
            });
        }
    }
}
