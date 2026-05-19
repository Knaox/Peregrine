<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\VersionChanger;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Plugins\VersionChanger\VersionChangerServiceProvider;

/**
 * Boots the Version Changer plugin inside tests :
 *
 *  - Registers the PSR-4 autoload (PluginBootstrap normally does this
 *    at runtime, but tests don't go through the activation flow).
 *  - Registers the ServiceProvider via `afterApplicationCreated` so
 *    routes / Filament / Invitations integration are wired.
 *  - Forces the plugin migrations to run inside the test schema, even
 *    when `RefreshDatabaseState::$migrated` has been flipped by a
 *    previous test — drops any leftover tables first to keep the run
 *    idempotent.
 *
 * Test classes apply this trait + `use RefreshDatabase`. Call
 * `bootVersionChangerPlugin()` BEFORE `parent::setUp()`.
 */
trait ActivatesVersionChangerPlugin
{
    protected function bootVersionChangerPlugin(): void
    {
        $repoRoot = __DIR__.'/../../../..';

        $loader = require $repoRoot.'/vendor/autoload.php';
        $loader->addPsr4(
            'Plugins\\VersionChanger\\',
            $repoRoot.'/plugins/version-changer/src/',
        );

        $migrationsPath = realpath($repoRoot.'/plugins/version-changer/src/Migrations');

        $this->afterApplicationCreated(function () use ($migrationsPath): void {
            $this->app->register(VersionChangerServiceProvider::class);

            // Drop & re-create the plugin tables every test boot. The
            // schema is wrapped in a SQLite transaction by RefreshDatabase
            // and rolled back per test, so this is idempotent.
            Schema::dropIfExists('change_version_logs');
            Schema::dropIfExists('version_changer_configs');
            Artisan::call('migrate', ['--path' => $migrationsPath, '--realpath' => true, '--force' => true]);
        });
    }
}
