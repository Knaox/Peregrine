<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Foundation : rename
 * `bridge_sync_logs.server_plan_id` → `bridge_sync_logs.server_configuration_id`.
 *
 * Drop the implicit FK created by `foreignId(...)->constrained()` first —
 * Laravel resolves it as `bridge_sync_logs_server_plan_id_foreign`. Recreate
 * after rename so the audit row is detached from a deleted configuration
 * (nullOnDelete) without losing the historic signature/payload trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bridge_sync_logs', function (Blueprint $table) {
            $table->dropForeign(['server_plan_id']);
        });

        Schema::table('bridge_sync_logs', function (Blueprint $table) {
            $table->renameColumn('server_plan_id', 'server_configuration_id');
        });

        Schema::table('bridge_sync_logs', function (Blueprint $table) {
            $table->foreign('server_configuration_id')
                ->references('id')
                ->on('server_configurations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bridge_sync_logs', function (Blueprint $table) {
            $table->dropForeign(['server_configuration_id']);
        });

        Schema::table('bridge_sync_logs', function (Blueprint $table) {
            $table->renameColumn('server_configuration_id', 'server_plan_id');
        });

        Schema::table('bridge_sync_logs', function (Blueprint $table) {
            $table->foreign('server_plan_id')
                ->references('id')
                ->on('server_configurations')
                ->nullOnDelete();
        });
    }
};
