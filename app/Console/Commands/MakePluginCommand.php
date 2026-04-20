<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakePluginCommand extends Command
{
    protected $signature = 'make:plugin {name : The plugin ID (kebab-case)}
                            {--N|display-name= : Display name}
                            {--D|description= : Plugin description}';

    protected $description = 'Scaffold a new plugin in the plugins/ directory';

    public function handle(Filesystem $files): int
    {
        $id = Str::kebab($this->argument('name'));
        $studly = Str::studly($id);
        $displayName = $this->option('display-name') ?? Str::title(str_replace('-', ' ', $id));
        $description = $this->option('description') ?? "A Peregrine plugin.";
        $basePath = base_path("plugins/{$id}");

        if ($files->isDirectory($basePath)) {
            $this->error("Plugin directory already exists: plugins/{$id}/");

            return self::FAILURE;
        }

        // Create directories
        $dirs = [
            "{$basePath}/src/Routes",
            "{$basePath}/src/Migrations",
            "{$basePath}/frontend/i18n",
            "{$basePath}/frontend/dist",
        ];

        foreach ($dirs as $dir) {
            $files->makeDirectory($dir, 0755, true);
        }

        // plugin.json
        $manifest = [
            'id' => $id,
            'name' => $displayName,
            'version' => '1.0.0',
            'description' => $description,
            'author' => 'Peregrine Team',
            'license' => 'MIT',
            'min_peregrine_version' => '1.0.0',
            'service_provider' => "{$studly}ServiceProvider",
            'frontend' => [
                'bundle' => 'frontend/dist/bundle.js',
                'nav' => [
                    [
                        'id' => $id,
                        'label' => $displayName,
                        'icon' => 'puzzle',
                        'route' => "/plugins/{$id}",
                    ],
                ],
            ],
            'settings_schema' => [],
        ];

        $files->put(
            "{$basePath}/plugin.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        // ServiceProvider
        $files->put("{$basePath}/src/{$studly}ServiceProvider.php", $this->serviceProviderStub($id, $studly));

        // Routes
        $files->put("{$basePath}/src/Routes/api.php", $this->routesStub($id));

        // Frontend index.tsx
        $files->put("{$basePath}/frontend/index.tsx", $this->frontendStub($id, $studly, $displayName));

        // i18n
        $files->put("{$basePath}/frontend/i18n/en.json", json_encode(['plugin_name' => $displayName], JSON_PRETTY_PRINT) . "\n");
        $files->put("{$basePath}/frontend/i18n/fr.json", json_encode(['plugin_name' => $displayName], JSON_PRETTY_PRINT) . "\n");

        // Icon placeholder
        $files->put("{$basePath}/icon.svg", $this->iconPlaceholder());

        // .gitkeep in dist
        $files->put("{$basePath}/frontend/dist/.gitkeep", '');

        $this->info("Plugin scaffolded: plugins/{$id}/");
        $this->line("  - ServiceProvider: src/{$studly}ServiceProvider.php");
        $this->line("  - Frontend entry:  frontend/index.tsx");
        $this->line("  - Manifest:        plugin.json");
        $this->newLine();
        $this->line("Next steps:");
        $this->line("  1. Code your backend in plugins/{$id}/src/");
        $this->line("  2. Code your frontend in plugins/{$id}/frontend/");
        $this->line("  3. Build: PLUGIN={$id} pnpm run build:plugin");
        $this->line("  4. Activate: php artisan plugin:activate {$id}");

        return self::SUCCESS;
    }

    private function serviceProviderStub(string $id, string $studly): string
    {
        return <<<PHP
        <?php

        namespace Plugins\\{$studly};

        use Illuminate\Support\ServiceProvider;
        use Illuminate\Support\Facades\Route;

        class {$studly}ServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                //
            }

            public function boot(): void
            {
                Route::prefix("api/plugins/{$id}")
                    ->middleware('api')
                    ->group(__DIR__ . '/Routes/api.php');
            }
        }

        PHP;
    }

    private function routesStub(string $id): string
    {
        return <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Route;

        // Plugin API routes — prefixed with /api/plugins/{id}
        Route::get('/', function () {
            return response()->json(['plugin' => true]);
        });

        PHP;
    }

    private function frontendStub(string $id, string $studly, string $name): string
    {
        return <<<TSX
        const React = (window as any).__PEREGRINE_SHARED__?.React;

        function {$studly}Page() {
            return React.createElement('div', {
                style: { padding: '2rem', textAlign: 'center' },
            }, [
                React.createElement('h1', {
                    key: 'title',
                    style: { fontSize: '1.5rem', fontWeight: 'bold', color: 'var(--color-text-primary)' },
                }, '{$name}'),
                React.createElement('p', {
                    key: 'desc',
                    style: { marginTop: '0.5rem', color: 'var(--color-text-secondary)' },
                }, 'Plugin is working!'),
            ]);
        }

        // Register the plugin
        (window as any).__PEREGRINE_PLUGINS__?.register('{$id}', {$studly}Page);

        TSX;
    }

    private function iconPlaceholder(): string
    {
        return <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
        </svg>

        SVG;
    }
}
