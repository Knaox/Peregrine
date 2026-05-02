<?php

namespace App\Console\Commands;

use App\Filament\Pages\Theme\ThemeDefaults;
use App\Services\SettingsService;
use App\Services\ThemeService;
use Illuminate\Console\Command;

/**
 * Snapshots the current theme configuration to a JSON file (or stdout) so
 * an admin can keep a backup before a risky edit, or carry their custom
 * theme to another Peregrine install.
 *
 * Payload shape mirrors `AdminThemeController::state()` for parity with
 * the studio's UI export when that lands later — same keys, same types.
 * Uploaded assets (login background images) are referenced by path, NOT
 * embedded as base64; an export is ~10 KB, not megabytes.
 *
 *   php artisan theme:export                         # writes to stdout
 *   php artisan theme:export --output=theme.json     # writes to file
 */
class ThemeExportCommand extends Command
{
    protected $signature = 'theme:export {--output= : Path to write JSON to (default: stdout)}';

    protected $description = 'Export the current theme configuration to JSON for backup or migration';

    public function handle(SettingsService $settings, ThemeService $theme): int
    {
        $payload = [
            '_meta' => [
                'exported_at' => now()->toIso8601String(),
                'app_url' => config('app.url'),
                'schema_version' => 1,
            ],
            'draft' => $this->buildDraft($settings),
            'card_config' => $theme->getCardConfig(),
            'sidebar_config' => $theme->getSidebarConfig(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $outputPath = $this->option('output');

        if (is_string($outputPath) && $outputPath !== '') {
            $bytes = file_put_contents($outputPath, $json);
            if ($bytes === false) {
                $this->error("Could not write to {$outputPath}.");
                return self::FAILURE;
            }
            $this->info("Wrote {$bytes} bytes to {$outputPath}.");
            return self::SUCCESS;
        }

        $this->getOutput()->write($json);
        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDraft(SettingsService $settings): array
    {
        $intKeys = [
            'theme_shadow_intensity',
            'theme_layout_header_height',
            'theme_sidebar_classic_width',
            'theme_sidebar_rail_width',
            'theme_sidebar_mobile_width',
            'theme_sidebar_blur_intensity',
            'theme_login_background_blur',
            'theme_login_carousel_interval',
            'theme_login_background_opacity',
            'theme_border_width',
            'theme_glass_blur_global',
        ];
        $boolKeys = [
            'theme_layout_header_sticky',
            'theme_sidebar_floating',
            'theme_login_carousel_enabled',
            'theme_login_carousel_random',
            'theme_page_console_fullwidth',
            'theme_page_files_fullwidth',
            'theme_page_dashboard_expanded',
            'theme_footer_enabled',
        ];
        $jsonArrayKeys = ['theme_login_background_images'];

        $draft = [];
        foreach (ThemeDefaults::COLORS as $key => $default) {
            $value = $settings->get($key, $default);
            if (in_array($key, $intKeys, true)) {
                $draft[$key] = (int) $value;
                continue;
            }
            if (in_array($key, $boolKeys, true)) {
                $draft[$key] = $value === '1' || $value === 'true' || $value === true;
                continue;
            }
            if (in_array($key, $jsonArrayKeys, true)) {
                $decoded = json_decode((string) $value, true);
                $draft[$key] = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
                continue;
            }
            $draft[$key] = $value;
        }

        $footerLinksRaw = (string) $settings->get('theme_footer_links', '[]');
        $footerLinks = json_decode($footerLinksRaw, true);
        $draft['theme_footer_links'] = is_array($footerLinks) ? $footerLinks : [];

        return $draft;
    }
}
