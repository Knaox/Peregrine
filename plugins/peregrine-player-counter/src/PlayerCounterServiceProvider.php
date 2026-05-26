<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\PeregrinePlayerCounter\Services\EggGameTypeResolver;
use Plugins\PeregrinePlayerCounter\Services\GameQueryClient;
use Plugins\PeregrinePlayerCounter\Services\PlayerCounterDocRenderer;
use Plugins\PeregrinePlayerCounter\Services\QueryAccessResolver;
use Plugins\PeregrinePlayerCounter\Services\QueryPortStrategy;
use Plugins\PeregrinePlayerCounter\Services\RconClient;
use Plugins\PeregrinePlayerCounter\Services\RconPlayerQuery;
use Plugins\PeregrinePlayerCounter\Services\ServerPlayerCountService;

/**
 * Boots the Player Counter plugin.
 *
 *  - Merges the static egg→game mapping config.
 *  - Binds the egg resolver, the sidecar HTTP client and the cached
 *    player-count service, plus the install-guide renderer.
 *  - Loads translations + the Filament page Blade views, and mounts the API
 *    routes under `/api/plugins/peregrine-player-counter`.
 *
 * The Filament settings page (`src/Filament/Pages/PlayerCounterSettingsPage.php`)
 * is auto-discovered by the core PluginBootstrap — nothing to register here.
 */
class PlayerCounterServiceProvider extends ServiceProvider
{
    public const PLUGIN_ID = 'peregrine-player-counter';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/game-query.php', self::PLUGIN_ID);

        $this->app->singleton(EggGameTypeResolver::class);
        $this->app->singleton(QueryPortStrategy::class);
        $this->app->singleton(GameQueryClient::class);
        $this->app->singleton(RconClient::class);
        $this->app->singleton(RconPlayerQuery::class);
        $this->app->singleton(QueryAccessResolver::class);
        $this->app->singleton(ServerPlayerCountService::class);
        $this->app->singleton(PlayerCounterDocRenderer::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', self::PLUGIN_ID);
        $this->loadViewsFrom(__DIR__.'/../resources/views', self::PLUGIN_ID);

        Route::prefix('api/plugins/'.self::PLUGIN_ID)
            ->middleware('api')
            ->group(__DIR__.'/Routes/api.php');
    }
}
