<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Foundation : add the technical-only naming fields and back-fill
 * them from the soon-to-be-dropped commercial `name` column. This MUST run
 * BEFORE the drop migration : after that migration, the source data is
 * gone.
 *
 * Fields :
 *  - `internal_name` : stable admin-facing identifier, used in name_template
 *    placeholders and outbound webhook payloads.
 *  - `technical_description` : free-form admin notes, never exposed via API.
 *  - `name_template` : Twig-like template applied at provision time to
 *    derive `Server.name` from user + configuration. Default placeholders
 *    : `{user.username}` and `{configuration.internal_name}`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_configurations', function (Blueprint $table) {
            $table->string('internal_name')->nullable()->after('id');
            $table->text('technical_description')->nullable()->after('internal_name');
            $table->string('name_template')->nullable()->after('technical_description');
        });

        // Back-fill internal_name from legacy commercial `name` if present,
        // else fall back to a deterministic 'config-{id}' placeholder so the
        // NOT NULL upgrade below never fails.
        if (Schema::hasColumn('server_configurations', 'name')) {
            DB::statement(
                "UPDATE server_configurations SET internal_name = COALESCE(NULLIF(name, ''), CONCAT('config-', id)) WHERE internal_name IS NULL"
            );
        } else {
            DB::statement(
                "UPDATE server_configurations SET internal_name = CONCAT('config-', id) WHERE internal_name IS NULL"
            );
        }

        DB::statement(
            "UPDATE server_configurations SET name_template = '{user.username}-{configuration.internal_name}' WHERE name_template IS NULL"
        );

        // Lock both fields as NOT NULL going forward.
        Schema::table('server_configurations', function (Blueprint $table) {
            $table->string('internal_name')->nullable(false)->change();
            $table->string('name_template')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('server_configurations', function (Blueprint $table) {
            $table->dropColumn(['internal_name', 'technical_description', 'name_template']);
        });
    }
};
