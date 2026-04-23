<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a suspended server for hard deletion after the grace period
 * configured in BridgeSettings (`bridge_grace_period_days`, default 14).
 *
 * Set when `customer.subscription.deleted` fires — server is suspended
 * immediately AND scheduled for deletion. The daily cron
 * `PurgeScheduledServerDeletionsJob` queries
 *   `WHERE scheduled_deletion_at <= NOW() AND status = 'suspended'`
 * and runs the actual delete (Pelican + local row).
 *
 * Indexed because the daily query scans on this column. Admins can clear
 * the value via the "Cancel scheduled deletion" Filament action (covered
 * in Sprint 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('scheduled_deletion_at')->nullable()->after('provisioning_error');
            $table->index('scheduled_deletion_at');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['scheduled_deletion_at']);
            $table->dropColumn('scheduled_deletion_at');
        });
    }
};
