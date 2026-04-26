<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Egg Config Editor v0.2 — drop the rules-based curation in favour of full
 * auto-detection driven by the plugin's i18n translation dictionary.
 *
 * Why : the rules table forced admins to declare every editable parameter
 * by hand (or import a preset). The new model parses the file at runtime,
 * lists every key found, and looks up its label/type/constraints in the
 * plugin's own i18n bundle. Admins now only declare which file to expose.
 *
 * Drops :
 *   - `egg_config_rules` table (all curation moves to the i18n dict)
 *
 * Simplifies `egg_config_files` :
 *   - Drops `display_name`, `description`, `sort_order` — no longer surfaced
 *     in the player UI; the file's basename + the dict's translated title
 *     do the job.
 *
 * Idempotent : guarded with `Schema::hasTable` / `Schema::hasColumn`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('egg_config_rules')) {
            Schema::drop('egg_config_rules');
        }

        if (Schema::hasTable('egg_config_files')) {
            Schema::table('egg_config_files', function (Blueprint $table) {
                if (Schema::hasColumn('egg_config_files', 'display_name')) {
                    $table->dropColumn('display_name');
                }
                if (Schema::hasColumn('egg_config_files', 'description')) {
                    $table->dropColumn('description');
                }
                if (Schema::hasColumn('egg_config_files', 'sort_order')) {
                    $table->dropColumn('sort_order');
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-reversible — the rules table was a different
        // architectural model. To roll back, re-create the original migration
        // and re-import data from a backup. We don't pretend to restore
        // information that was deleted.
    }
};
