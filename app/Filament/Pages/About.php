<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnitEnum;

class About extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-information-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'About & Updates';

    protected static ?string $navigationLabel = 'About';

    protected string $view = 'filament.pages.about';

    public string $currentVersion = '';

    public ?string $latestVersion = null;

    public ?string $latestReleaseUrl = null;

    public ?string $latestReleasedAt = null;

    public bool $updateAvailable = false;

    public bool $isDocker = false;

    public ?string $checkError = null;

    public function mount(): void
    {
        $this->currentVersion = (string) config('panel.version', '0.0.0');
        $this->isDocker = (bool) config('panel.docker', false);
        $this->refreshLatest(useCache: true);
    }

    public function refreshUpdate(): void
    {
        $this->refreshLatest(useCache: false);

        Notification::make()
            ->title($this->updateAvailable ? 'Update available' : 'Up to date')
            ->body($this->updateAvailable
                ? "Version {$this->latestVersion} is available."
                : "You're running the latest version ({$this->currentVersion}).")
            ->success()
            ->send();
    }

    private function refreshLatest(bool $useCache): void
    {
        $repo = (string) config('panel.update_repo', 'Knaox/Peregrine');
        $cacheKey = "panel.update_check:{$repo}";

        if ($useCache) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $this->applyLatest($cached);
                return;
            }
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Peregrine-Panel'])
                ->get("https://api.github.com/repos/{$repo}/releases/latest");

            if (! $response->ok()) {
                $this->checkError = "GitHub API returned {$response->status()}.";
                return;
            }

            $data = [
                'tag_name' => (string) $response->json('tag_name', ''),
                'html_url' => (string) $response->json('html_url', ''),
                'published_at' => (string) $response->json('published_at', ''),
            ];

            Cache::put($cacheKey, $data, now()->addHours(1));
            $this->applyLatest($data);
        } catch (\Throwable $e) {
            $this->checkError = $e->getMessage();
        }
    }

    /**
     * @param  array{tag_name: string, html_url: string, published_at: string}  $data
     */
    private function applyLatest(array $data): void
    {
        $this->latestVersion = ltrim($data['tag_name'], 'v') ?: null;
        $this->latestReleaseUrl = $data['html_url'] ?: null;
        $this->latestReleasedAt = $data['published_at'] ?: null;
        $this->updateAvailable = $this->latestVersion !== null
            && version_compare($this->normalizeVersion($this->latestVersion), $this->normalizeVersion($this->currentVersion), '>');
        $this->checkError = null;
    }

    /**
     * Normalise pre-release tags ("1.0.0-alpha.1") to a form version_compare handles predictably.
     */
    private function normalizeVersion(string $version): string
    {
        return strtolower(trim($version));
    }

    /**
     * @return array<int, array{title: string, description: string, command: string}>
     */
    public function getUpdateCommands(): array
    {
        if ($this->isDocker) {
            return [
                [
                    'title' => 'Pull latest images and restart',
                    'description' => 'Fetches the latest published images and recreates the running containers.',
                    'command' => 'docker compose pull && docker compose up -d',
                ],
                [
                    'title' => 'Run migrations inside the container',
                    'description' => 'Applies any new database migrations shipped with this release.',
                    'command' => 'docker compose exec app php artisan migrate --force',
                ],
            ];
        }

        return [
            [
                'title' => 'Pull latest code',
                'description' => 'Fetches the latest source from the main branch.',
                'command' => 'git pull',
            ],
            [
                'title' => 'Install PHP + JS dependencies',
                'description' => 'Installs any added composer/pnpm dependencies.',
                'command' => 'composer install --no-dev --optimize-autoloader && pnpm install',
            ],
            [
                'title' => 'Build frontend assets',
                'description' => 'Rebuilds the Vite bundle with production optimizations.',
                'command' => 'pnpm run build',
            ],
            [
                'title' => 'Migrate database + refresh caches',
                'description' => 'Applies pending migrations and rebuilds config/route caches.',
                'command' => 'php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan queue:restart',
            ],
        ];
    }
}
