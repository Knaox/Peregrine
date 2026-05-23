<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration;

use App\Models\Server;
use App\Services\Plugin\ManifestEnricherRegistry;
use App\Services\Plugin\StartupVariableClaimRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\EasyConfiguration\Console\CheckBoostsCommand;
use Plugins\EasyConfiguration\Console\SyncTemplatesCommand;
use Plugins\EasyConfiguration\Services\Parsing\ParserRegistry;
use Plugins\EasyConfiguration\Services\Permissions\PermissionContributor;
use Plugins\EasyConfiguration\Services\Templates\TemplateLoader;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use Plugins\EasyConfiguration\Services\Templates\TemplateSchemaValidator;
use Plugins\EasyConfiguration\Services\Templates\TemplateStorage;
use Throwable;

/**
 * Boots the Easy Configuration plugin.
 *
 *  - Binds the parser + template registries (the shared, container-resolved
 *    singletons the rest of the plugin depends on)
 *  - Loads migrations + translations, mounts routes under
 *    `/api/plugins/easy-configuration`
 *  - Registers the `easyconfig` subuser permission group via the Invitations
 *    3-guard pattern (no-op + graceful when Invitations isn't active)
 *  - Enriches the manifest so the overview section only mounts on servers whose
 *    egg has at least one template (cached 60s, self-healing rebuild)
 */
class EasyConfigurationServiceProvider extends ServiceProvider
{
    private const PLUGIN_ID = 'easy-configuration';

    public function register(): void
    {
        $this->app->singleton(ParserRegistry::class);
        $this->app->singleton(TemplateSchemaValidator::class);

        $this->app->singleton(TemplateStorage::class, static fn (): TemplateStorage => new TemplateStorage(
            storage_path('app/easy-config/templates'),
        ));

        // TemplateLoader (storage + validator) and TemplateRegistry (loader)
        // auto-resolve their constructor dependencies from the bindings above.
        $this->app->singleton(TemplateLoader::class);
        $this->app->singleton(TemplateRegistry::class);

        $this->commands([SyncTemplatesCommand::class, CheckBoostsCommand::class]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', self::PLUGIN_ID);

        Route::prefix('api/plugins/'.self::PLUGIN_ID)
            ->middleware('api')
            ->group(__DIR__.'/Routes/api.php');

        $this->app->make(PermissionContributor::class)->register();
        $this->registerManifestEnricher();
        $this->registerStartupVariableClaim();
        $this->registerSchedule();
    }

    /**
     * Declare to the core every env var a template parameter links to, so the
     * core startup-variables page ("Server configuration") badges them as linked
     * — they're edited there, and hidden from this plugin's own editor to avoid a
     * duplicate surface. Best-effort + guarded.
     */
    private function registerStartupVariableClaim(): void
    {
        if (! class_exists(StartupVariableClaimRegistry::class)) {
            return;
        }

        try {
            StartupVariableClaimRegistry::getInstance()->register(self::PLUGIN_ID, function (Server $server): array {
                try {
                    return $this->app->make(TemplateRegistry::class)->linkedEnvVars((int) $server->egg_id);
                } catch (Throwable) {
                    return [];
                }
            });
        } catch (Throwable) {
            // Claiming is best-effort; a failure just means no variables are hidden.
        }
    }

    /**
     * Tick the boost scheduler every minute (single-server, non-overlapping):
     * applies due boosts and ends expired ones via queued jobs.
     */
    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('easy-config:check-boosts')
                ->everyMinute()
                ->withoutOverlapping()
                ->onOneServer();
        });
    }

    /**
     * Inject `requires_egg_ids` into the `easy-config` server_home_section so the
     * React shell hides the card on servers whose egg has no template. The list
     * is the union of every valid template's target_eggs, cached 60s; the cached
     * closure also re-syncs the template cache so disk changes self-heal.
     */
    private function registerManifestEnricher(): void
    {
        if (! class_exists(ManifestEnricherRegistry::class)) {
            return;
        }

        try {
            ManifestEnricherRegistry::getInstance()->register(self::PLUGIN_ID, function (array $manifest): array {
                $eggIds = Cache::remember('easy_config.targeted_eggs', 60, function (): array {
                    try {
                        $registry = $this->app->make(TemplateRegistry::class);
                        $registry->rebuild();

                        return $registry->targetedEggIds();
                    } catch (Throwable) {
                        return [];
                    }
                });

                foreach (($manifest['server_home_sections'] ?? []) as $i => $section) {
                    if (($section['id'] ?? null) === 'easy-config') {
                        $manifest['server_home_sections'][$i]['requires_egg_ids'] = $eggIds;
                    }
                }

                return $manifest;
            });
        } catch (Throwable) {
            // Manifest enrichment is best-effort; the section just shows on every server.
        }
    }
}
