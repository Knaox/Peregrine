<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror parity for the sub-users page. The plugin route at
 * `plugins/invitations/src/Routes/api.php:103` reads `email` and `uuid`
 * from each subuser record to merge local pivot permissions and to call
 * back into Pelican (DELETE/PATCH require the UUID, not the numeric id).
 *
 * The original schema only carried numeric `pelican_subuser_id` +
 * `pelican_user_id` + permissions, so DB-read mode rendered without
 * emails or actionable buttons. This migration adds the three string
 * fields to bring the mirror payload to feature parity with the live
 * Pelican Client API response (`/api/client/servers/{id}/users`).
 *
 * Lives in core (not in the plugin's Migrations folder) so it runs on
 * every deploy without requiring an admin to re-activate the plugin.
 * No-op when the invitations plugin isn't installed.
 */
return new class extends Migration
{
    private const TABLE = 'invitations_pelican_subusers';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'email')) {
                $table->string('email')->nullable()->after('pelican_user_id')->index();
            }
            if (! Schema::hasColumn(self::TABLE, 'uuid')) {
                $table->string('uuid', 64)->nullable()->after('email')->index();
            }
            if (! Schema::hasColumn(self::TABLE, 'username')) {
                $table->string('username')->nullable()->after('uuid');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            foreach (['email', 'uuid', 'username'] as $col) {
                if (Schema::hasColumn(self::TABLE, $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
