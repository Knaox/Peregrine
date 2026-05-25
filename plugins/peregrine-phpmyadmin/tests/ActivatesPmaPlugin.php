<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Plugins\PeregrinePhpmyadmin\PhpMyAdminServiceProvider;

/**
 * Boots the plugin inside tests (PSR-4 is already wired by bootstrap.php):
 *  - registers the ServiceProvider after the app is created, so routes are
 *    mounted and services bound;
 *  - (re)creates the plugin table in the test schema each boot — idempotent,
 *    since RefreshDatabase rolls the per-test transaction back.
 *
 * Call `bootPmaPlugin()` BEFORE `parent::setUp()`.
 */
trait ActivatesPmaPlugin
{
    protected function bootPmaPlugin(): void
    {
        $migrationsPath = realpath(__DIR__.'/../src/Migrations');

        $this->afterApplicationCreated(function () use ($migrationsPath): void {
            $this->app->register(PhpMyAdminServiceProvider::class);

            Schema::dropIfExists('pma_launch_logs');
            Artisan::call('migrate', ['--path' => $migrationsPath, '--realpath' => true, '--force' => true]);
        });
    }
}
