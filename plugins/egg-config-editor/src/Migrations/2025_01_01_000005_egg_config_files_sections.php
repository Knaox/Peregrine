<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Egg Config Editor v0.5 — add a `sections` JSON column to let admins
 * whitelist which INI sections to expose. Empty / null = expose all.
 *
 * Use case : ARK's GameUserSettings.ini contains many sections beyond the
 * useful `[ServerSettings]` one — `[ScalabilityGroups]`, `[/Script/...]`,
 * engine internals — which players should not touch. Listing
 * `["ServerSettings", "MessageOfTheDay"]` here keeps the rest hidden
 * (and untouched on save, thanks to the parser's preserve-unknown-lines).
 *
 * Has no effect on Properties / JSON files (no native section concept).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('egg_config_files')) {
            return;
        }

        if (! Schema::hasColumn('egg_config_files', 'sections')) {
            Schema::table('egg_config_files', function (Blueprint $table) {
                $table->json('sections')->nullable()->after('file_paths');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('egg_config_files') && Schema::hasColumn('egg_config_files', 'sections')) {
            Schema::table('egg_config_files', function (Blueprint $table) {
                $table->dropColumn('sections');
            });
        }
    }
};
