<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Egg Config Editor v1.0 — add a `non_boolean_keys` JSON column. Stores the
 * config keys for which the auto-detected `boolean` type was wrong (the value
 * literally looks like "true"/"false" but the user wants to edit it as raw
 * text). Players with eggconfig.write toggle this from the editor; the list
 * is shared across every server using this egg config file row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('egg_config_files')) {
            return;
        }

        if (! Schema::hasColumn('egg_config_files', 'non_boolean_keys')) {
            Schema::table('egg_config_files', function (Blueprint $table) {
                $table->json('non_boolean_keys')->nullable()->after('sections');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('egg_config_files') && Schema::hasColumn('egg_config_files', 'non_boolean_keys')) {
            Schema::table('egg_config_files', function (Blueprint $table) {
                $table->dropColumn('non_boolean_keys');
            });
        }
    }
};
