<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relaxes the invitations subuser mirror so the SubuserMirrorBackfiller can
 * populate rows from the Pelican Client API — which returns email + UUID +
 * permissions but NOT the numeric `subuser_id` exposed by webhooks.
 *
 * Lives in core (not in the plugin's Migrations folder) so it runs on
 * every deploy without requiring an admin to re-activate the plugin.
 * No-op when the invitations plugin isn't installed.
 *
 * Before : `pelican_subuser_id` was UNIQUE NOT NULL — only webhook-driven
 * inserts could land. Backfill on existing installs had no place to go.
 *
 * After  : `pelican_subuser_id` becomes NULLABLE; the natural unique key
 * is the (pelican_server_id, pelican_user_id) pair which both sources
 * (webhook + backfill via email→user lookup) can produce. When a webhook
 * arrives later for a backfilled row, the listener matches on the pair
 * and fills in `pelican_subuser_id`.
 */
return new class extends Migration
{
    private const TABLE = 'invitations_pelican_subusers';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        // The original plugin migration declared `pelican_subuser_id` as
        // UNIQUE NOT NULL — but some installs only have the column without
        // the index (interrupted migration, manual schema fixup). Drop the
        // unique tolerantly so the migration is safe everywhere.
        try {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(['pelican_subuser_id']);
            });
        } catch (\Throwable) {
            // Index didn't exist — nothing to drop. Continue.
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unsignedBigInteger('pelican_subuser_id')->nullable()->change();
        });

        // Same defensive guard — re-running the migration must be a no-op.
        try {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(['pelican_server_id', 'pelican_user_id'], 'invitations_subusers_server_user_unique');
            });
        } catch (\Throwable) {
            // Composite unique already in place from a prior run.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique('invitations_subusers_server_user_unique');
            $table->unsignedBigInteger('pelican_subuser_id')->nullable(false)->change();
            $table->unique('pelican_subuser_id');
        });
    }
};
