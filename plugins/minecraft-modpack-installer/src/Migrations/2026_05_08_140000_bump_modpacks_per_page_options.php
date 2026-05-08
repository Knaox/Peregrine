<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Page-size whitelist bumped from [6, 12, 24] → [10, 25, 50] on
 * 2026-05-08 (operator request : denser listings, easier to scan a
 * crowded modpack catalog at a glance).
 *
 * Existing rows storing the old values would otherwise fall back to
 * the new hardcoded default 25 via `ModpackConfig::modpacksPerPage()`
 * — silently, with no admin notice. We migrate them in place to the
 * nearest neighbour so the operator's intent (smaller / larger lists)
 * survives. The mapping is a 1:1 replacement, not a math transform :
 *   6  → 10  ("a small list")
 *   12 → 25  ("a medium list", default)
 *   24 → 50  ("a large list")
 *
 * Down-migration is symmetric. Both paths are idempotent : running
 * twice on already-migrated data is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modpack_configs')) {
            return;
        }

        DB::table('modpack_configs')->where('modpacks_per_page', 6)->update(['modpacks_per_page' => 10]);
        DB::table('modpack_configs')->where('modpacks_per_page', 12)->update(['modpacks_per_page' => 25]);
        DB::table('modpack_configs')->where('modpacks_per_page', 24)->update(['modpacks_per_page' => 50]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('modpack_configs')) {
            return;
        }

        DB::table('modpack_configs')->where('modpacks_per_page', 10)->update(['modpacks_per_page' => 6]);
        DB::table('modpack_configs')->where('modpacks_per_page', 25)->update(['modpacks_per_page' => 12]);
        DB::table('modpack_configs')->where('modpacks_per_page', 50)->update(['modpacks_per_page' => 24]);
    }
};
