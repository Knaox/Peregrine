<?php

namespace Tests\Feature\Console;

use App\Filament\Pages\Theme\ThemeDefaults;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Smoke tests for `theme:export` + `theme:import`. Exercises the
 * roundtrip happy path, the dry-run-by-default behaviour, and the CLI's
 * defensive validation — a malicious export must not bypass the same
 * `theme_custom_css` blacklist enforced by SaveThemeRequest.
 */
class ThemeExportImportTest extends TestCase
{
    use RefreshDatabase;

    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPath = tempnam(sys_get_temp_dir(), 'peregrine-theme-test-');
        app(SettingsService::class)->clearCache();
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempPath)) {
            unlink($this->tempPath);
        }
        parent::tearDown();
    }

    public function test_export_writes_json_with_meta_and_all_draft_keys(): void
    {
        Artisan::call('theme:export', ['--output' => $this->tempPath]);

        $this->assertFileExists($this->tempPath);
        $payload = json_decode((string) file_get_contents($this->tempPath), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('_meta', $payload);
        $this->assertArrayHasKey('draft', $payload);
        $this->assertArrayHasKey('card_config', $payload);
        $this->assertArrayHasKey('sidebar_config', $payload);

        // schema_version is part of the contract — bumps must be intentional.
        $this->assertSame(1, $payload['_meta']['schema_version']);

        // Every key in ThemeDefaults::COLORS must be in the exported draft.
        foreach (array_keys(ThemeDefaults::COLORS) as $key) {
            $this->assertArrayHasKey($key, $payload['draft'], "draft missing {$key}");
        }
        $this->assertArrayHasKey('theme_footer_links', $payload['draft']);
    }

    public function test_roundtrip_export_import_is_idempotent(): void
    {
        Artisan::call('theme:export', ['--output' => $this->tempPath]);

        $exitCode = Artisan::call('theme:import', [
            'file' => $this->tempPath,
            '--force' => true,
        ]);
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Would change 0 of', $output);
    }

    public function test_import_persists_changes(): void
    {
        // Hand-craft a minimal export with a custom theme_primary.
        $payload = [
            '_meta' => ['schema_version' => 1],
            'draft' => [
                'theme_primary' => '#abcdef',
                'theme_radius' => '0.5rem',
            ],
            'card_config' => ThemeDefaults::CARD_CONFIG,
            'sidebar_config' => ThemeDefaults::SIDEBAR_CONFIG,
        ];
        file_put_contents($this->tempPath, json_encode($payload));

        $exitCode = Artisan::call('theme:import', [
            'file' => $this->tempPath,
            '--force' => true,
        ]);
        $this->assertSame(0, $exitCode);

        $this->assertSame('#abcdef', Setting::where('key', 'theme_primary')->value('value'));
        $this->assertSame('0.5rem', Setting::where('key', 'theme_radius')->value('value'));
    }

    public function test_import_rejects_blacklisted_custom_css(): void
    {
        $payload = [
            '_meta' => ['schema_version' => 1],
            'draft' => [
                'theme_custom_css' => '@import url("https://evil.tld/x.css");',
            ],
            'card_config' => ThemeDefaults::CARD_CONFIG,
            'sidebar_config' => ThemeDefaults::SIDEBAR_CONFIG,
        ];
        file_put_contents($this->tempPath, json_encode($payload));

        $exitCode = Artisan::call('theme:import', [
            'file' => $this->tempPath,
            '--force' => true,
        ]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Refusing to import', Artisan::output());
        // The malicious payload must NOT have been persisted.
        $stored = Setting::where('key', 'theme_custom_css')->value('value');
        $this->assertNotSame('@import url("https://evil.tld/x.css");', $stored);
    }

    public function test_import_fails_on_missing_file(): void
    {
        $exitCode = Artisan::call('theme:import', [
            'file' => '/tmp/this-file-does-not-exist-' . uniqid(),
            '--force' => true,
        ]);
        $this->assertSame(1, $exitCode);
    }

    public function test_import_fails_on_malformed_json(): void
    {
        file_put_contents($this->tempPath, '{not json');

        $exitCode = Artisan::call('theme:import', [
            'file' => $this->tempPath,
            '--force' => true,
        ]);
        $this->assertSame(1, $exitCode);
    }
}
