<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller;

use App\Models\Server;
use App\Services\Plugin\ManifestEnricherRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\MinecraftModpackInstaller\Console\ImportEgg;
use Plugins\MinecraftModpackInstaller\Console\ReconcileStaleInstallations;
use Plugins\MinecraftModpackInstaller\Models\ModpackConfig;
use Plugins\MinecraftModpackInstaller\Pelican\PelicanClient;
use Plugins\MinecraftModpackInstaller\Services\EligibilityService;
use Plugins\MinecraftModpackInstaller\Services\JavaCompatibilityMatrix;
use Plugins\MinecraftModpackInstaller\Services\ModpackProviderRegistry;
use Plugins\MinecraftModpackInstaller\Services\Providers\AtlauncherProvider;
use Plugins\MinecraftModpackInstaller\Services\Providers\CurseForgeProvider;
use Plugins\MinecraftModpackInstaller\Services\Providers\FtbProvider;
use Plugins\MinecraftModpackInstaller\Services\Providers\ModrinthProvider;
use Plugins\MinecraftModpackInstaller\Services\Providers\TechnicProvider;
use Plugins\MinecraftModpackInstaller\Services\Providers\VoidsWrathProvider;

/**
 * Boots the Modpack Installer plugin. Mirrors the structure of the bundled
 * invitations / egg-config-editor plugins:
 *
 *  - Loads its own migrations
 *  - Mounts its routes under `/api/plugins/minecraft-modpack-installer`
 *  - Registers six provider singletons + the registry
 *  - When the Invitations plugin is present, registers the three modpack
 *    permissions in its shared PermissionRegistry, gated by the eligibility
 *    filter so they only show up on whitelisted servers
 *  - Schedules the `modpacks:reconcile-stale-installations` artisan command
 *    every five minutes (timeout safety net)
 *  - Auto-discovers the Filament settings page on modern cores; falls back
 *    to manual registration on older ones (parallels egg-config-editor)
 */
class MinecraftModpackInstallerServiceProvider extends ServiceProvider
{
    private const PLUGIN_ID = 'minecraft-modpack-installer';

    public function register(): void
    {
        // Plugin-bundled defaults for the Java compatibility matrix —
        // rules / images / default_java. The Filament admin page can
        // override any of these per-row in `modpack_configs`.
        $this->mergeConfigFrom(
            __DIR__.'/../config/java-compatibility.php',
            'modpack-installer.java',
        );

        $this->app->singleton(PelicanClient::class);

        // Resolve `ModpackConfig::current()` lazily so the matrix sees
        // a fresh singleton row per request / queue handle. Each job
        // invocation builds a new container, so cache lifetime here is
        // safely bounded.
        $this->app->bind(JavaCompatibilityMatrix::class, function (Application $app): JavaCompatibilityMatrix {
            return new JavaCompatibilityMatrix(
                ModpackConfig::current(),
                $app['config'],
            );
        });

        $this->app->singleton(ModpackProviderRegistry::class, function (Application $app): ModpackProviderRegistry {
            // Per provider ToS (notably Modrinth), every outbound request
            // must carry an identifying User-Agent. We surface the panel's
            // own URL so abuse reports route back to the operator who
            // actually issued the request — never a hardcoded vendor URL.
            $userAgent = sprintf(
                'PeregrineModpackInstaller/%s (+%s)',
                (string) ($app['config']->get('app.version', '1.0')),
                (string) ($app['config']->get('app.url', 'https://peregrine.local')),
            );

            $registry = new ModpackProviderRegistry();
            $http = $app->make(HttpFactory::class);
            $cache = $app->make('cache.store');

            $registry->register(new ModrinthProvider($http, $cache, $userAgent));
            $registry->register(new CurseForgeProvider(
                $http,
                $cache,
                $userAgent,
                $app->make(\Plugins\MinecraftModpackInstaller\Services\ModpackSettingsService::class),
            ));
            $registry->register(new AtlauncherProvider($http, $cache, $userAgent));
            $registry->register(new FtbProvider($http, $cache, $userAgent));
            $registry->register(new TechnicProvider($http, $cache, $userAgent));
            $registry->register(new VoidsWrathProvider($http, $cache, $userAgent));

            return $registry;
        });

        $this->commands([
            ReconcileStaleInstallations::class,
            ImportEgg::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
        $this->loadViewsFrom(__DIR__.'/../views', 'plugins.minecraft-modpack-installer');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'minecraft-modpack-installer');

        Route::prefix('api/plugins/'.self::PLUGIN_ID)
            ->middleware('api')
            ->group(__DIR__.'/Routes/api.php');

        $this->registerSubuserPermissions();
        $this->registerSchedule();
        $this->registerFilamentFallback();
        $this->registerManifestEnricher();
        $this->registerEventListeners();
    }

    /**
     * Listen to core lifecycle events the plugin needs to react to. Safe to
     * call when the event class isn't shipped by the host — the listener is
     * skipped silently (mirrors the Invitations permission-registry guard).
     *
     * Currently:
     *
     *  - `App\Events\ServerReinstallStarting` (added in Peregrine after this
     *    plugin shipped) — fires when an operator clicks "Reinstall" on the
     *    server homepage. The plugin drops its modpack_installations row so
     *    the modpack tab stops showing the pack as installed.
     */
    private function registerEventListeners(): void
    {
        if (! class_exists('\\App\\Events\\ServerReinstallStarting')) {
            return;
        }

        Event::listen(
            \App\Events\ServerReinstallStarting::class,
            \Plugins\MinecraftModpackInstaller\Listeners\ClearModpackOnReinstall::class,
        );
    }

    /**
     * Inject `requires_egg_ids` into the `modpacks` server_sidebar_entry so
     * the React shell hides the tab on servers whose egg isn't in the
     * admin whitelist. Cached 60s and busted by the settings save flow.
     *
     * Spec §4.1 : tab visible only when (a) server's egg type is whitelisted
     * AND (b) user has at least the read permission. (a) is enforced here ;
     * (b) is enforced by the manifest's `required_permission` field.
     */
    private function registerManifestEnricher(): void
    {
        ManifestEnricherRegistry::getInstance()->register(self::PLUGIN_ID, function (array $manifest): array {
            $settings = $this->app->make(\Plugins\MinecraftModpackInstaller\Services\ModpackSettingsService::class);

            $eggIds = Cache::remember(
                'modpack_settings.whitelisted_egg_ids',
                60,
                static function () use ($settings): array {
                    try {
                        return $settings->whitelistedEggIds();
                    } catch (\Throwable) {
                        return [];
                    }
                }
            );

            $route = Cache::remember(
                'modpack_settings.page_route',
                60,
                static function () use ($settings): string {
                    try {
                        return $settings->pageRoute();
                    } catch (\Throwable) {
                        return '/modpacks';
                    }
                }
            );

            $entries = $manifest['server_sidebar_entries'] ?? [];
            foreach ($entries as $idx => $entry) {
                if (($entry['id'] ?? null) === 'modpacks') {
                    $entries[$idx]['requires_egg_ids'] = $eggIds;
                    $entries[$idx]['route_suffix'] = $route;
                }
            }
            $manifest['server_sidebar_entries'] = $entries;

            return $manifest;
        });
    }

    /**
     * Register the three modpack permissions in the Invitations plugin's
     * PermissionRegistry. The plugin is optional — the registration is a
     * silent no-op when Invitations is absent (per the spec).
     *
     * Permissions are filtered per-server : the group only surfaces in the
     * picker for servers whose egg is whitelisted by the admin (see
     * /admin/modpack-settings).
     */
    private function registerSubuserPermissions(): void
    {
        $registryClass = '\\Plugins\\Invitations\\Services\\PermissionRegistry';

        if (! class_exists($registryClass)) {
            return;
        }

        $eligibility = $this->app->make(EligibilityService::class);

        $registryClass::getInstance()->registerGroup(
            groupKey: 'modpack',
            groupLabel: [
                'en' => 'Modpacks',
                'fr' => 'Modpacks',
            ],
            permissions: [
                'modpack.read' => [
                    'en' => 'View modpacks',
                    'fr' => 'Consulter les modpacks',
                ],
                'modpack.install' => [
                    'en' => 'Install modpacks',
                    'fr' => 'Installer des modpacks',
                ],
                'modpack.uninstall' => [
                    'en' => 'Uninstall modpacks',
                    'fr' => 'Désinstaller des modpacks',
                ],
            ],
            availableForServer: function (Server $server) use ($eligibility): bool {
                try {
                    return $eligibility->isEligible($server);
                } catch (\Throwable) {
                    return false;
                }
            },
        );
    }

    /**
     * Schedule the timeout-reconciliation safety net every 5 minutes,
     * single-server, non-overlapping. This is the only thing standing
     * between an interrupted install and a server stuck "active forever".
     */
    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('modpacks:reconcile-stale-installations')
                ->everyFiveMinutes()
                ->withoutOverlapping()
                ->onOneServer();
        });
    }

    /**
     * On modern Peregrine cores, Filament resources/pages declared by the
     * plugin are auto-discovered (PluginManager::contributeToFilamentPanel).
     * On older cores, register the settings resource manually via a `booted`
     * callback. Mirrors the pattern in egg-config-editor.
     */
    private function registerFilamentFallback(): void
    {
        if (! class_exists(\Filament\Facades\Filament::class)) {
            return;
        }

        $this->app->booted(function (): void {
            try {
                \Filament\Facades\Filament::getDefaultPanel()->resources([
                    \Plugins\MinecraftModpackInstaller\Filament\Resources\ModpackConfigResource::class,
                ]);
            } catch (\Throwable) {
                // No default panel registered yet — skip silently.
            }
        });
    }
}
