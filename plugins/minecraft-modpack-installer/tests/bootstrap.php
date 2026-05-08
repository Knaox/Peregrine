<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Plugin-local Test Bootstrap
|--------------------------------------------------------------------------
|
| Plugin namespaces are registered at runtime by `App\Services\Plugin\
| PluginBootstrap` — Composer's PSR-4 map doesn't know about them. For
| standalone unit tests (which bypass the Laravel application boot to
| keep things fast and dependency-free), we wire the plugin's PSR-4
| prefix directly into the autoloader here.
|
| This mirrors the production wiring exactly:
|   PluginBootstrap.php:63 → $loader->addPsr4("Plugins\\{Studly}\\", $srcPath);
|
*/

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require __DIR__.'/../../../vendor/autoload.php';

$autoloader->addPsr4(
    'Plugins\\MinecraftModpackInstaller\\',
    __DIR__.'/../src',
);

$autoloader->addPsr4(
    'Plugins\\MinecraftModpackInstaller\\Tests\\',
    __DIR__,
);
