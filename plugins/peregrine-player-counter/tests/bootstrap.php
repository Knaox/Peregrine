<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

/*
|--------------------------------------------------------------------------
| Plugin-local Test Bootstrap
|--------------------------------------------------------------------------
|
| Plugin namespaces are registered at runtime by `App\Services\Plugin\
| PluginBootstrap` — Composer's PSR-4 map doesn't know about them. The egg
| resolver and the query-port strategy are pure (no Laravel boot), so we wire
| the plugin's PSR-4 prefix directly into the autoloader here.
|
| Mirrors the production wiring: PluginBootstrap adds
|   $loader->addPsr4("Plugins\\PeregrinePlayerCounter\\", $srcPath);
*/

/** @var ClassLoader $autoloader */
$autoloader = require __DIR__.'/../../../vendor/autoload.php';

$autoloader->addPsr4('Plugins\\PeregrinePlayerCounter\\', __DIR__.'/../src');
$autoloader->addPsr4('Plugins\\PeregrinePlayerCounter\\Tests\\', __DIR__);
