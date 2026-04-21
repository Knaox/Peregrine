<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_user_id');
            $table->string('provider_email');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index(['user_id', 'provider']);
        });

        // Data migration: port any existing users.oauth_provider/oauth_id rows.
        // Safe on fresh installs — the WHERE clause yields zero rows.
        if (
            Schema::hasColumn('users', 'oauth_provider')
            && Schema::hasColumn('users', 'oauth_id')
        ) {
            // CURRENT_TIMESTAMP is SQL-standard and portable across MySQL +
            // SQLite (used by the test suite). NOW() is MySQL-only.
            DB::statement(
                'INSERT INTO oauth_identities (user_id, provider, provider_user_id, provider_email, created_at, updated_at)
                 SELECT id, oauth_provider, oauth_id, email, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                 FROM users
                 WHERE oauth_provider IS NOT NULL AND oauth_id IS NOT NULL'
            );

            // Drop composite index BEFORE renaming — otherwise MySQL keeps the
            // index on the old column names and later schema ops get messy.
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['oauth_provider', 'oauth_id']);
            });

            // RENAME (not drop): safety net across étape A → C. Drop happens in
            // étape E once the full OAuth refactor is validated in staging.
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('oauth_provider', 'oauth_provider_legacy');
                $table->renameColumn('oauth_id', 'oauth_id_legacy');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('users', 'oauth_provider_legacy')
            && Schema::hasColumn('users', 'oauth_id_legacy')
        ) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('oauth_provider_legacy', 'oauth_provider');
                $table->renameColumn('oauth_id_legacy', 'oauth_id');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->index(['oauth_provider', 'oauth_id']);
            });
        }

        Schema::dropIfExists('oauth_identities');
    }
};
