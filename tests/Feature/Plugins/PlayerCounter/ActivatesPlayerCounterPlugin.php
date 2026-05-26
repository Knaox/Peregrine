<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\PlayerCounter;

/**
 * Boots the Player Counter plugin inside tests:
 *
 *  - Registers the PSR-4 autoload (PluginBootstrap does this at runtime, but
 *    tests don't go through the activation flow).
 *  - Merges the plugin's static config under its key so the egg→game resolver
 *    sees the real rules + Steam heuristic.
 *
 * The resolver is pure (no DB), so this trait deliberately stays light — no
 * migrations, no ServiceProvider boot. Call `bootPlayerCounterPlugin()` BEFORE
 * `parent::setUp()`.
 */
trait ActivatesPlayerCounterPlugin
{
    protected function bootPlayerCounterPlugin(): void
    {
        $repoRoot = __DIR__.'/../../../..';

        $loader = require $repoRoot.'/vendor/autoload.php';
        $loader->addPsr4(
            'Plugins\\PeregrinePlayerCounter\\',
            $repoRoot.'/plugins/peregrine-player-counter/src/',
        );

        $this->afterApplicationCreated(function () use ($repoRoot): void {
            config()->set(
                'peregrine-player-counter',
                require $repoRoot.'/plugins/peregrine-player-counter/config/game-query.php',
            );
        });
    }
}
