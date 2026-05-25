<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

/*
|--------------------------------------------------------------------------
| Plugin-local Test Bootstrap
|--------------------------------------------------------------------------
|
| Plugin namespaces are registered at runtime by PluginBootstrap — Composer's
| PSR-4 map doesn't know about them. Wire the plugin's prefixes here so the
| (app-booting) feature tests can resolve plugin classes. Mirrors production:
| PluginBootstrap adds $loader->addPsr4("Plugins\\PeregrinePhpmyadmin\\", src).
*/

/** @var ClassLoader $autoloader */
$autoloader = require __DIR__.'/../../../vendor/autoload.php';

$autoloader->addPsr4('Plugins\\PeregrinePhpmyadmin\\', __DIR__.'/../src');
$autoloader->addPsr4('Plugins\\PeregrinePhpmyadmin\\Tests\\', __DIR__);
