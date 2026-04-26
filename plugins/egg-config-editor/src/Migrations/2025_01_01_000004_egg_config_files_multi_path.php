<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Egg Config Editor v0.4 — turn `file_path` into a multi-value `file_paths`
 * JSON column so a single entry can declare several candidate paths the
 * plugin will try in order.
 *
 * Real-world need : games like ARK ship the same `GameUserSettings.ini`
 * either under `.../LinuxServer/...` or `.../WindowsServer/...` depending on
 * the container OS. Admin lists both paths once; the plugin auto-resolves
 * to the one that actually exists at read time.
 *
 * Steps :
 *   1. Add `file_paths` JSON column nullable
 *   2. Backfill from `file_path` (each row becomes [file_path])
 *   3. Drop `file_path`
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

        if (! Schema::hasColumn('egg_config_files', 'file_paths')) {
            Schema::table('egg_config_files', function (Blueprint $table) {
                $table->json('file_paths')->nullable()->after('egg_ids');
            });
        }

        if (Schema::hasColumn('egg_config_files', 'file_path')) {
            $rows = DB::table('egg_config_files')
                ->whereNull('file_paths')
                ->get(['id', 'file_path']);
            foreach ($rows as $row) {
                $path = (string) $row->file_path;
                DB::table('egg_config_files')
                    ->where('id', $row->id)
                    ->update(['file_paths' => json_encode($path !== '' ? [$path] : [])]);
            }

            Schema::table('egg_config_files', function (Blueprint $table) {
                $table->dropColumn('file_path');
            });
        }
    }

    public function down(): void
    {
        // Non-reversible : we'd lose every path beyond the first if a row
        // has multiple. Restore from backup if rolling back.
    }
};
