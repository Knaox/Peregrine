<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;

/**
 * Minimal container + config bootstrap for the pure-unit tests. The egg
 * resolver and query-port strategy read their rules through the `config()`
 * helper (which resolves `app('config')`), so we stand up a bare container
 * with the plugin's own config files merged under its namespace — exactly as
 * the service provider does at runtime — without booting all of Laravel.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container;
        $container->instance('config', new Repository([
            PlayerCounterServiceProvider::PLUGIN_ID => require __DIR__.'/../config/game-query.php',
        ]));

        Container::setInstance($container);
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);

        parent::tearDown();
    }
}
