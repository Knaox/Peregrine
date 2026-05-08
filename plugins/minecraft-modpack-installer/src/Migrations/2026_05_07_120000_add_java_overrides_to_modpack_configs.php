<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add three nullable override columns to modpack_configs.
 *
 *  - `java_rules`   : full replacement of the plugin's bundled rule list
 *  - `java_images`  : per-key override of the bundled Docker image map
 *  - `default_java` : override of the bundled default Java major
 *
 * All three are nullable on purpose — when null/empty, the JavaCompatibility-
 * Matrix service falls back to `config('modpack-installer.java.*')`, which
 * is loaded from `plugins/.../config/java-compatibility.php`. Operators only
 * fill these in to override (e.g. private registry mirror, exotic MC build).
 *
 * Additive only — no data migration needed, no rollback risk on existing
 * rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modpack_configs')) {
            // The plugin's earlier migration is responsible for table
            // creation; bail silently when called out of order so a
            // fresh-install scenario doesn't crash.
            return;
        }

        Schema::table('modpack_configs', function (Blueprint $table): void {
            if (! Schema::hasColumn('modpack_configs', 'java_rules')) {
                $table->json('java_rules')->nullable()->after('cache_ttl_seconds');
            }
            if (! Schema::hasColumn('modpack_configs', 'java_images')) {
                $table->json('java_images')->nullable()->after('java_rules');
            }
            if (! Schema::hasColumn('modpack_configs', 'default_java')) {
                $table->unsignedTinyInteger('default_java')->nullable()->after('java_images');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('modpack_configs')) {
            return;
        }

        Schema::table('modpack_configs', function (Blueprint $table): void {
            $columns = [];
            foreach (['java_rules', 'java_images', 'default_java'] as $column) {
                if (Schema::hasColumn('modpack_configs', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
