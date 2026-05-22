<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Easy Configuration plugin.
 *
 *  - Loads its own migrations (template cache, boost schedules/history, copy log)
 *  - Loads its backend translations under the `easy-configuration` namespace
 *  - Mounts its routes under `/api/plugins/easy-configuration`
 *
 * The heavier wiring (parser/template registries, subuser permissions via the
 * Invitations 3-guard pattern, the boost scheduler command, and the manifest
 * enricher that injects `requires_egg_ids` from the template cache) is added in
 * later phases. The scaffold deliberately keeps `register()`/`boot()` minimal so
 * activating the plugin has zero side effects beyond migrations + routes.
 */
class EasyConfigurationServiceProvider extends ServiceProvider
{
    private const PLUGIN_ID = 'easy-configuration';

    public function register(): void
    {
        // Service bindings (ParserRegistry, TemplateRegistry, config/boost/copy
        // services) are registered in later phases.
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', self::PLUGIN_ID);

        Route::prefix('api/plugins/'.self::PLUGIN_ID)
            ->middleware('api')
            ->group(__DIR__.'/Routes/api.php');
    }
}
