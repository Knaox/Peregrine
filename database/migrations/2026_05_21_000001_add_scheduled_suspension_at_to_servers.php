<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an active server for suspension at the end of its paid period when
 * the customer disables auto-renew (`cancel_at_period_end=true`).
 *
 * Phase 1 of the two-phase cancellation lifecycle :
 *   T0  cancel_at_period_end  → scheduled_suspension_at = period end,
 *                               scheduled_deletion_at  = period end + grace.
 *   T1  scheduled_suspension_at <= now  → `SuspendScheduledServersJob` cron
 *                               suspends the server (status='suspended') and
 *                               clears this column, keeping scheduled_deletion_at.
 *   T2  scheduled_deletion_at  <= now  → `PurgeScheduledServerDeletionsJob`
 *                               cron hard-deletes (Pelican + local row).
 *
 * Indexed because the hourly suspension sweep scans on this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('scheduled_suspension_at')->nullable()->after('scheduled_deletion_at');
            $table->index('scheduled_suspension_at');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['scheduled_suspension_at']);
            $table->dropColumn('scheduled_suspension_at');
        });
    }
};
