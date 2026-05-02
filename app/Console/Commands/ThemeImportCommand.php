<?php

namespace App\Console\Commands;

use App\Filament\Pages\Theme\ThemeDefaults;
use App\Services\SettingsService;
use App\Services\ThemeService;
use Illuminate\Console\Command;

/**
 * Applies a theme JSON exported by `theme:export` to the current install.
 *
 * Defaults to a dry-run that shows the diff (count of keys that would
 * change) and asks for confirmation. Pass `--force` for non-interactive
 * usage in deploy scripts.
 *
 *   php artisan theme:import theme.json
 *   php artisan theme:import theme.json --force
 *
 * Re-uses `SaveThemeRequest::customCssRule()` semantics by inlining the
 * blacklist regex check — a malicious export must not be able to bypass
 * the validator just because it travels through the CLI.
 */
class ThemeImportCommand extends Command
{
    protected $signature = 'theme:import {file : Path to a JSON file produced by theme:export} {--force : Skip dry-run / confirmation}';

    protected $description = 'Import a theme JSON file (output of theme:export) into this install';

    /**
     * Patterns mirrored from SaveThemeRequest::customCssRule(). A CLI
     * import must enforce the same sanitisation as the HTTP endpoint.
     */
    private const CUSTOM_CSS_BLACKLIST = [
        '/@import\b/i',
        '/url\s*\(\s*["\']?\s*https?:/i',
        '/url\s*\(\s*["\']?\s*\/\//i',
        '/expression\s*\(/i',
        '/behavior\s*:/i',
        '/javascript\s*:/i',
        '/<\s*script\b/i',
    ];

    public function handle(SettingsService $settings, ThemeService $theme): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File not found or not readable: {$path}");
            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error("Could not read {$path}.");
            return self::FAILURE;
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload) || ! isset($payload['draft']) || ! is_array($payload['draft'])) {
            $this->error('Invalid theme export: missing or malformed `draft` key.');
            return self::FAILURE;
        }

        if (! isset($payload['_meta'])) {
            $this->warn('No `_meta` block — proceeding but the file may not have come from theme:export.');
        }

        // Validate custom CSS before doing any write.
        $custom = $payload['draft']['theme_custom_css'] ?? '';
        if (is_string($custom) && $custom !== '') {
            foreach (self::CUSTOM_CSS_BLACKLIST as $regex) {
                if (preg_match($regex, $custom)) {
                    $this->error('Refusing to import: theme_custom_css matches a blacklisted pattern (@import / external url / script / expression / behavior / javascript:). Edit the file and retry.');
                    return self::FAILURE;
                }
            }
        }

        // Diff: count keys that would change vs current state.
        $diff = $this->computeDiff($settings, $payload['draft']);
        $changedKeys = count($diff);
        $totalKeys = count($payload['draft']);

        $this->line("Would change {$changedKeys} of {$totalKeys} theme settings.");
        if ($changedKeys === 0) {
            $this->info('Nothing to do — current state already matches the import.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Apply these changes?', true)) {
            $this->line('Aborted.');
            return self::SUCCESS;
        }

        // Apply.
        foreach ($payload['draft'] as $key => $value) {
            if (! array_key_exists($key, ThemeDefaults::COLORS) && $key !== 'theme_footer_links') {
                $this->warn("Skipping unknown key: {$key}");
                continue;
            }
            $settings->set($key, $this->normalise($key, $value));
        }

        if (isset($payload['card_config']) && is_array($payload['card_config'])) {
            $settings->set('card_server_config', json_encode($payload['card_config']));
        }
        if (isset($payload['sidebar_config']) && is_array($payload['sidebar_config'])) {
            $settings->set('sidebar_server_config', json_encode($payload['sidebar_config']));
        }

        $settings->clearCache();
        $theme->clearCache();

        $this->info("Applied {$changedKeys} changes. Caches flushed.");
        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<int, string>  keys whose import value differs from current
     */
    private function computeDiff(SettingsService $settings, array $draft): array
    {
        $changed = [];
        foreach ($draft as $key => $value) {
            $current = $settings->get($key);
            $normalisedNext = $this->normalise($key, $value);
            $normalisedCurrent = $current === null ? null : (string) $current;
            if ($normalisedCurrent !== $normalisedNext) {
                $changed[] = $key;
            }
        }
        return $changed;
    }

    private function normalise(string $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        return (string) $value;
    }
}
