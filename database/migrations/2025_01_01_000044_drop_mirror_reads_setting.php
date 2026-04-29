<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cleans up the `mirror_reads_enabled` row in the `settings` table —
 * leftover from the cancelled "Lecture DB locale" feature.
 *
 * Reasoning : the controllers no longer read this flag. Leaving it in
 * the table is harmless but confuses operators inspecting the
 * settings store and shows up in audit logs as a configurable that
 * does nothing. Idempotent : safe to re-run, no-op when missing.
 *
 * Down migration intentionally a no-op — this row carries no data we'd
 * want to restore.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('key', 'mirror_reads_enabled')
            ->delete();
    }

    public function down(): void
    {
        // No-op : the rollback feature is gone, restoring the row would
        // serve no purpose.
    }
};
