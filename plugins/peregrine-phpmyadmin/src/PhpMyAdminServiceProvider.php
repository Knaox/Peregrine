<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\PeregrinePhpmyadmin\Services\PmaCredentialResolver;
use Plugins\PeregrinePhpmyadmin\Services\PmaDocRenderer;
use Plugins\PeregrinePhpmyadmin\Services\PmaTokenStore;

/**
 * Boots the phpMyAdmin integration plugin.
 *
 *  - Binds the credential resolver, the one-shot signon token store and the
 *    install-guide markdown renderer.
 *  - Loads migrations + translations + the Filament page Blade view, and
 *    mounts the API routes under `/api/plugins/peregrine-phpmyadmin`.
 *
 * The Filament settings page (`src/Filament/Pages/PmaPluginSettings.php`) is
 * auto-discovered by the core PluginBootstrap — nothing to register here.
 */
class PhpMyAdminServiceProvider extends ServiceProvider
{
    public const PLUGIN_ID = 'peregrine-phpmyadmin';

    public function register(): void
    {
        $this->app->singleton(PmaTokenStore::class);
        $this->app->singleton(PmaCredentialResolver::class);
        $this->app->singleton(PmaDocRenderer::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', self::PLUGIN_ID);
        $this->loadViewsFrom(__DIR__.'/../resources/views', self::PLUGIN_ID);

        Route::prefix('api/plugins/'.self::PLUGIN_ID)
            ->middleware('api')
            ->group(__DIR__.'/Routes/api.php');
    }
}
