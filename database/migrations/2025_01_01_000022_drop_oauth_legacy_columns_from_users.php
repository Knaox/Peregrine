<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Étape E cleanup: drop the legacy oauth_provider_legacy /
     * oauth_id_legacy columns that migration 000019 left behind as a
     * rollback safety net during the oauth_identities rollout.
     *
     * Data lives in oauth_identities since 000019 and has been validated in
     * staging — the snapshot SQL produced by `php artisan auth:backup-oauth-legacy`
     * remains the last-resort rollback.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'oauth_provider_legacy')) {
                $table->dropColumn('oauth_provider_legacy');
            }
            if (Schema::hasColumn('users', 'oauth_id_legacy')) {
                $table->dropColumn('oauth_id_legacy');
            }
        });
    }

    public function down(): void
    {
        // Recreate the columns as nullable strings. Data is NOT restored —
        // refer to storage/backups/oauth_legacy_pre_migration_*.sql for a
        // forensic snapshot of what was dropped.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'oauth_provider_legacy')) {
                $table->string('oauth_provider_legacy')->nullable();
            }
            if (! Schema::hasColumn('users', 'oauth_id_legacy')) {
                $table->string('oauth_id_legacy')->nullable();
            }
        });
    }
};
