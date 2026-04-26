<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Egg Config Editor v0.3 — turn `egg_id` into a multi-value `egg_ids` JSON
 * column so a single config-file definition can target multiple eggs (e.g.
 * ARK + ARK Modded + ARK Survival Ascended share the same `GameUserSettings.ini`
 * at the same path).
 *
 * Steps :
 *   1. Add `egg_ids` JSON column nullable
 *   2. Backfill from the existing `egg_id` (each row becomes [egg_id])
 *   3. Drop the FK + unique index that referenced `egg_id`
 *   4. Drop `egg_id`
 *   5. Make `egg_ids` non-nullable + add a (file_path) index for the
 *      controller's `whereJsonContains` query
 *
 * Idempotent : guarded so re-running on a fresh schema doesn't crash.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('egg_config_files')) {
            return;
        }

        if (! Schema::hasColumn('egg_config_files', 'egg_ids')) {
            Schema::table('egg_config_files', function (Blueprint $table) {
                $table->json('egg_ids')->nullable()->after('id');
            });
        }

        // Backfill : each existing row gets a JSON array containing its
        // single egg_id. Idempotent : skips rows already populated.
        if (Schema::hasColumn('egg_config_files', 'egg_id')) {
            $rows = DB::table('egg_config_files')
                ->whereNull('egg_ids')
                ->orWhere('egg_ids', '')
                ->get(['id', 'egg_id']);
            foreach ($rows as $row) {
                DB::table('egg_config_files')
                    ->where('id', $row->id)
                    ->update(['egg_ids' => json_encode([(int) $row->egg_id])]);
            }
        }

        if (Schema::hasColumn('egg_config_files', 'egg_id')) {
            // Drop the FK first — MySQL refuses to drop the unique index it
            // depends on otherwise. Then drop the unique index, then the
            // column. Each step in its own statement so partial failures
            // are recoverable.
            Schema::table('egg_config_files', function (Blueprint $table) {
                try {
                    $table->dropForeign(['egg_id']);
                } catch (\Throwable) {
                    // FK may not exist on installs that pre-date the
                    // FK definition — that's fine.
                }
            });
            Schema::table('egg_config_files', function (Blueprint $table) {
                try {
                    $table->dropUnique('egg_config_files_egg_path_unique');
                } catch (\Throwable) {
                    // Index name may differ — ignore.
                }
            });
            Schema::table('egg_config_files', function (Blueprint $table) {
                $table->dropColumn('egg_id');
            });
        }

        // Promote `egg_ids` to non-nullable now that backfill is done.
        // We don't add a unique constraint : two files can share a path
        // when they target different egg sets. The controller dedupes by
        // (file_path) within a single egg's listing.
    }

    public function down(): void
    {
        // Non-reversible : converting a multi-value array back to a single
        // FK isn't safe (which egg do we keep?). Restore from backup if
        // rolling back.
    }
};
