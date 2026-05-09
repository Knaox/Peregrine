<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the `bridge_sync_logs` table.
 *
 * The legacy Bridge subsystem (push-based plan sync via PlanSyncController +
 * VerifyBridgeSignature middleware) was removed in alpha.7 — nothing writes
 * to this table anymore, and the matching `/admin/bridge-sync-logs` Filament
 * resource is gone too. `dropIfExists` so fresh installs (where the create
 * migration was deleted in the same release) stay green.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bridge_sync_logs');
    }

    public function down(): void
    {
        // No down migration — the legacy schema is intentionally one-way.
        // Restoring the table without the controller / middleware that fed
        // it would only create a confusingly-empty audit page.
    }
};
