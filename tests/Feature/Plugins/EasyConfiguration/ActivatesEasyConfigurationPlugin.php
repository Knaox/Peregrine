<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\EasyConfiguration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Plugins\EasyConfiguration\EasyConfigurationServiceProvider;

/**
 * Boots the Easy Configuration plugin inside tests (mirrors the Version
 * Changer trait): PSR-4 autoload + ServiceProvider registration via
 * `afterApplicationCreated` (routes/bindings), and the plugin migrations
 * re-run idempotently inside the test schema.
 *
 * Test classes apply this trait + `use RefreshDatabase`, and call
 * `bootEasyConfigurationPlugin()` BEFORE `parent::setUp()`.
 */
trait ActivatesEasyConfigurationPlugin
{
    protected function bootEasyConfigurationPlugin(): void
    {
        $repoRoot = __DIR__.'/../../../..';

        $loader = require $repoRoot.'/vendor/autoload.php';
        $loader->addPsr4(
            'Plugins\\EasyConfiguration\\',
            $repoRoot.'/plugins/easy-configuration/src/',
        );

        $migrationsPath = realpath($repoRoot.'/plugins/easy-configuration/src/Migrations');

        $this->afterApplicationCreated(function () use ($migrationsPath): void {
            $this->app->register(EasyConfigurationServiceProvider::class);

            Schema::dropIfExists('easy_config_boost_history');
            Schema::dropIfExists('easy_config_boost_schedules');
            Schema::dropIfExists('easy_config_copy_log');
            Schema::dropIfExists('easy_config_templates');
            Artisan::call('migrate', ['--path' => $migrationsPath, '--realpath' => true, '--force' => true]);
        });
    }
}
