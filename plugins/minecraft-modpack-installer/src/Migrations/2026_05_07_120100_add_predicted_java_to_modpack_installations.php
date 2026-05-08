<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the modpack loader and the *predicted* Java major on the
 * installation row.
 *
 *  - `loader` : forge / fabric / neoforge / quilt — captured from the
 *    ModpackVersion DTO at startInstall, used by the matrix to pick the
 *    right Java major before the install starts. Stored on the row because
 *    the DTO doesn't survive serialization through the queue and the job
 *    reads only an installation id.
 *
 *  - `predicted_java_version` : output of JavaCompatibilityMatrix at
 *    startInstall. Used as the install-phase image (replaces the previous
 *    hardcoded Java 21) AND as the MCJars fallback in finalizeInstall
 *    (replaces the previous blind 17 fallback).
 *
 * Both nullable so legacy rows stay readable; the jobs treat NULL as
 * "fall back to the config default".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modpack_installations')) {
            return;
        }

        Schema::table('modpack_installations', function (Blueprint $table): void {
            if (! Schema::hasColumn('modpack_installations', 'loader')) {
                $table->string('loader', 20)->nullable()->after('version_label');
            }
            if (! Schema::hasColumn('modpack_installations', 'predicted_java_version')) {
                $table->unsignedTinyInteger('predicted_java_version')->nullable()->after('java_version');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('modpack_installations')) {
            return;
        }

        Schema::table('modpack_installations', function (Blueprint $table): void {
            $columns = [];
            foreach (['loader', 'predicted_java_version'] as $column) {
                if (Schema::hasColumn('modpack_installations', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
