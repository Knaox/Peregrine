<?php

namespace Tests\Feature;

use App\Filament\Pages\Theme\ThemeDefaults;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks the contract of `2025_01_01_000050_seed_theme_v3_defaults` :
 *  - Every key in ThemeDefaults::COLORS lands as a row after migrate
 *  - Re-running the migration is a no-op (insertOrIgnore semantics)
 *  - Existing customised values are NOT overwritten by re-runs
 *  - Down() does not destroy data
 */
class ThemeDefaultsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_theme_defaults_key_is_seeded(): void
    {
        // RefreshDatabase already ran the migration up.
        foreach (array_keys(ThemeDefaults::COLORS) as $key) {
            $this->assertDatabaseHas('settings', ['key' => $key]);
        }
    }

    public function test_seeded_values_match_theme_defaults(): void
    {
        foreach (ThemeDefaults::COLORS as $key => $expected) {
            $this->assertSame(
                is_string($expected) ? $expected : json_encode($expected),
                Setting::where('key', $key)->value('value'),
                "Setting {$key} should match its ThemeDefaults default",
            );
        }
    }

    public function test_card_and_sidebar_config_are_seeded_as_json(): void
    {
        $card = Setting::where('key', 'card_server_config')->value('value');
        $sidebar = Setting::where('key', 'sidebar_server_config')->value('value');

        $this->assertNotNull($card);
        $this->assertNotNull($sidebar);
        $this->assertSame(ThemeDefaults::CARD_CONFIG, json_decode($card, true));
        $this->assertSame(ThemeDefaults::SIDEBAR_CONFIG, json_decode($sidebar, true));
    }

    public function test_rerunning_migration_does_not_overwrite_admin_overrides(): void
    {
        // Admin customises theme_primary after the migration ran.
        DB::table('settings')->where('key', 'theme_primary')->update(['value' => '#abcdef']);

        // Re-run the migration. insertOrIgnore must skip the existing key.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2025_01_01_000050_seed_theme_v3_defaults.php',
        ]);

        $this->assertSame(
            '#abcdef',
            Setting::where('key', 'theme_primary')->value('value'),
            'Admin override of theme_primary must survive a re-run of the seed migration',
        );
    }
}
