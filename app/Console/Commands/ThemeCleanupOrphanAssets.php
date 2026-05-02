<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sweep theme upload slots and delete files that are no longer referenced
 * by any `theme_*` setting. The Theme Studio upload endpoint stores each
 * new asset under `storage/app/public/branding/{slot}/<random>.<ext>` and
 * intentionally does NOT delete the previous file at upload time — an
 * admin can revert a setting and still have the older image available
 * during a single editing session. Without periodic cleanup, however,
 * every login-background experiment leaves a permanent file behind.
 *
 * Scheduled weekly in `routes/console.php`. Safe to run manually:
 *   php artisan theme:cleanup-orphan-assets
 *   php artisan theme:cleanup-orphan-assets --dry-run
 */
class ThemeCleanupOrphanAssets extends Command
{
    protected $signature = 'theme:cleanup-orphan-assets {--dry-run : List orphans without deleting}';

    protected $description = 'Delete theme upload files no longer referenced by any setting';

    /**
     * Settings keyed by storage slot. Each entry says which setting key(s)
     * may reference files under `branding/{slot}/`. Single-value settings
     * hold one path string; array settings hold a JSON array of paths.
     *
     * @var array<string, array{single: array<int, string>, array: array<int, string>}>
     */
    private const SLOT_SETTING_MAP = [
        'login_background' => [
            'single' => ['theme_login_background_image'],
            'array'  => ['theme_login_background_images'],
        ],
    ];

    public function handle(SettingsService $settings): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');
        $totalDeleted = 0;
        $totalKept = 0;

        foreach (self::SLOT_SETTING_MAP as $slot => $keys) {
            $directory = "branding/{$slot}";
            if (! $disk->directoryExists($directory)) {
                continue;
            }

            $referenced = $this->collectReferencedPaths($settings, $keys);
            $files = $disk->files($directory);

            foreach ($files as $relativePath) {
                $publicPath = '/storage/' . $relativePath;
                if (in_array($publicPath, $referenced, true)) {
                    $totalKept++;
                    continue;
                }
                if ($dryRun) {
                    $this->line("orphan: {$publicPath}");
                } else {
                    $disk->delete($relativePath);
                    $this->line("deleted: {$publicPath}");
                }
                $totalDeleted++;
            }
        }

        $verb = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("{$verb} {$totalDeleted} orphan file(s); kept {$totalKept} referenced.");
        return self::SUCCESS;
    }

    /**
     * @param  array{single: array<int, string>, array: array<int, string>}  $keys
     * @return array<int, string>  Distinct list of public paths still referenced.
     */
    private function collectReferencedPaths(SettingsService $settings, array $keys): array
    {
        $paths = [];
        foreach ($keys['single'] as $key) {
            $value = $settings->get($key);
            if (is_string($value) && $value !== '') {
                $paths[] = $value;
            }
        }
        foreach ($keys['array'] as $key) {
            $raw = $settings->get($key);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        if (is_string($item) && $item !== '') {
                            $paths[] = $item;
                        }
                    }
                }
            }
        }
        return array_values(array_unique($paths));
    }
}
