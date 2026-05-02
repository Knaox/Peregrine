<?php

use App\Filament\Pages\Theme\ThemeDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the `settings` rows for every key in ThemeDefaults::COLORS so the
 * Theme Studio editor binds against real DB rows instead of relying on PHP
 * fallbacks for the 33 Vague 3 keys (layout / sidebar / login / per-page /
 * footer / refinements). Without this, an admin upgrading from a Vague 1
 * install never sees these keys materialise until they save once.
 *
 * Idempotent via `insertOrIgnore` — existing rows (admin overrides) are
 * preserved. Down() is a no-op: rolling back must not destroy admin
 * configuration. The shop seeder keeps doing its job for the brand colours
 * — this migration just guarantees the structural defaults are present.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach (ThemeDefaults::COLORS as $key => $default) {
            $rows[] = [
                'key' => $key,
                'value' => is_string($default) ? $default : json_encode($default),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Structural JSON-blob settings the studio also needs in place.
        // Same idempotent pattern — never overwrites a customised payload.
        $rows[] = [
            'key' => 'card_server_config',
            'value' => json_encode(ThemeDefaults::CARD_CONFIG),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $rows[] = [
            'key' => 'sidebar_server_config',
            'value' => json_encode(ThemeDefaults::SIDEBAR_CONFIG),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $rows[] = [
            'key' => 'theme_footer_links',
            'value' => json_encode([]),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('settings')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        // Intentionally no-op. Rolling back this migration must not
        // delete admin-customised theme rows. If a clean wipe is needed,
        // truncate the table directly or use `migrate:fresh`.
    }
};
