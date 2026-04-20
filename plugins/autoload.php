<?php

/**
 * Plugin autoloader — loaded from bootstrap/app.php BEFORE Laravel boots.
 *
 * Three layers of protection:
 *   1. Composer addPsr4() — works with optimize-autoloader Level 1
 *   2. Composer addClassMap() — works with classmap-authoritative Level 2
 *   3. spl_autoload_register() — independent fallback, always works
 *
 * Reads the plugins/ directory directly (no DB needed for autoloading).
 */

(static function (): void {
    $pluginsDir = __DIR__;

    if (! is_dir($pluginsDir)) {
        return;
    }

    // Scan plugins/ directory for plugin folders with src/ subdirectory
    $pluginDirs = [];

    foreach (scandir($pluginsDir) as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'autoload.php') {
            continue;
        }

        $srcPath = $pluginsDir . '/' . $entry . '/src/';

        if (is_dir($srcPath)) {
            $studlyId = str_replace(' ', '', ucwords(str_replace('-', ' ', $entry)));
            $pluginDirs[$studlyId] = $srcPath;
        }
    }

    if (empty($pluginDirs)) {
        return;
    }

    // Layer 1 + 2: Try Composer ClassLoader (addPsr4 + addClassMap).
    // If Composer hasn't booted yet (very early CLI), silently fall through to Layer 3.
    try {
        $loaders = class_exists(\Composer\Autoload\ClassLoader::class)
            ? \Composer\Autoload\ClassLoader::getRegisteredLoaders()
            : [];
        $loader = $loaders ? reset($loaders) : null;

        if ($loader) {
            foreach ($pluginDirs as $studlyId => $srcPath) {
                $namespace = "Plugins\\{$studlyId}\\";

                // Layer 1: PSR-4
                $loader->addPsr4($namespace, $srcPath);

                // Layer 2: ClassMap
                $classMap = [];
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($srcPath, FilesystemIterator::SKIP_DOTS),
                );

                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    $relativePath = str_replace($srcPath, '', $file->getPathname());
                    $className = $namespace . str_replace(
                        ['/', '.php'],
                        ['\\', ''],
                        $relativePath,
                    );
                    $classMap[$className] = $file->getPathname();
                }

                if (! empty($classMap)) {
                    $loader->addClassMap($classMap);
                }
            }
        }
    } catch (\Throwable) {
        // Layer 3 below is always registered as a safety net.
    }

    // Layer 3: spl_autoload_register — independent fallback, always works
    spl_autoload_register(static function (string $class) use ($pluginsDir): void {
        if (! str_starts_with($class, 'Plugins\\')) {
            return;
        }

        // Plugins\Invitations\Mail\ServerInvitationMail
        // → plugins/invitations/src/Mail/ServerInvitationMail.php
        $parts = explode('\\', $class);
        array_shift($parts); // Remove "Plugins"
        $studlyId = (string) array_shift($parts); // "Invitations"

        // StudlyCase → kebab-case: "Invitations" → "invitations"
        $pluginId = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $studlyId));

        $relativePath = implode('/', $parts) . '.php';
        $filePath = $pluginsDir . '/' . $pluginId . '/src/' . $relativePath;

        if (file_exists($filePath)) {
            require $filePath;
        }
    });
})();
