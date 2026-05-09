<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Foundation : rename `servers.plan_id` → `servers.server_configuration_id`.
 *
 * Drop FK first (Laravel/Doctrine cannot rename a constrained column in
 * place on most drivers), rename, then recreate the FK against the
 * already-renamed `server_configurations` table with `nullOnDelete` —
 * matches the `null on delete` semantics introduced in
 * 2025_01_01_000023_extend_server_plans_for_bridge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->renameColumn('plan_id', 'server_configuration_id');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->foreign('server_configuration_id')
                ->references('id')
                ->on('server_configurations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['server_configuration_id']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->renameColumn('server_configuration_id', 'plan_id');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->foreign('plan_id')
                ->references('id')
                ->on('server_configurations')
                ->nullOnDelete();
        });
    }
};
