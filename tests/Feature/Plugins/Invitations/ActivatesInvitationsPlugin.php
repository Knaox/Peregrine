<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\Invitations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Plugins\Invitations\InvitationsServiceProvider;

/**
 * Boots the Invitations plugin inside tests:
 *
 *  - Registers the PSR-4 autoload (PluginBootstrap does this at runtime, but
 *    tests don't go through the activation flow).
 *  - Registers the ServiceProvider via `afterApplicationCreated` so routes /
 *    permissions / mail views are wired.
 *  - Runs the plugin migrations into the test schema (drops the table first so
 *    the boot stays idempotent across tests under RefreshDatabase).
 *
 * Test classes apply this trait + `use RefreshDatabase` and call
 * `bootInvitationsPlugin()` BEFORE `parent::setUp()`.
 */
trait ActivatesInvitationsPlugin
{
    protected function bootInvitationsPlugin(): void
    {
        $repoRoot = __DIR__.'/../../../..';

        $loader = require $repoRoot.'/vendor/autoload.php';
        $loader->addPsr4('Plugins\\Invitations\\', $repoRoot.'/plugins/invitations/src/');

        $migrationsPath = realpath($repoRoot.'/plugins/invitations/src/Migrations');

        $this->afterApplicationCreated(function () use ($migrationsPath): void {
            $this->app->register(InvitationsServiceProvider::class);

            Schema::dropIfExists('server_invitations');
            Artisan::call('migrate', ['--path' => $migrationsPath, '--realpath' => true, '--force' => true]);
        });
    }
}
