<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable batch grouping key to `server_invitations` so a single
 * "invite to many servers" action can issue ONE email whose accept link
 * authorizes every invitation in the batch at once.
 *
 * Purely additive: `token` stays UNIQUE per row and single-server invites keep
 * `batch_id = NULL`, so every existing query and the whole single-invite flow
 * behave exactly as before. `is_batch_leader` marks the one row whose token is
 * shipped in the shared email. Rollback just drops the two columns — no data
 * loss for single-server invitations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_invitations', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('token')->index();
            $table->boolean('is_batch_leader')->default(false)->after('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('server_invitations', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'is_batch_leader']);
        });
    }
};
