<?php

namespace Plugins\EggConfigEditor;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\EggConfigEditor\Filament\Resources\EggConfigFileResource;

/**
 * Egg Config Editor — bootstraps the plugin once it's activated by the admin.
 *
 *  - Loads its own migrations (`src/Migrations/`).
 *  - Registers the `/api/plugins/egg-config-editor/*` routes the React
 *    bundle calls.
 *  - Best-effort fallback registration of the Filament resource (only
 *    matters when running against an older Peregrine core that doesn't
 *    yet have `PluginManager::contributeToFilamentPanel()`). The current
 *    core auto-discovers Filament resources from active plugins at panel
 *    construction time, so this fallback is a no-op for fresh installs.
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
}
