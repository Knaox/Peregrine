<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the `theme_revision` setting used by AdminThemeController for
 * optimistic-lock save semantics. Starts at "0"; the controller increments
 * it on every successful save (studio + Filament + reset).
 *
 * The studio reads `theme_revision` from /state, posts it back as
 * `expected_revision`, and the controller returns 409 Conflict if the
 * stored revision has advanced — meaning another admin or another path
 * (Filament, CLI import) published in between.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'theme_revision',
            'value' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally no-op — see seed_theme_v3_defaults migration.
    }
};
