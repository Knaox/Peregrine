<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the provisioning state machine to `servers` :
 *
 *  - `idempotency_key` : unique per provisioning attempt. Lets ProvisionServerJob
 *    dedupe retries — if a row already exists for this key, the job knows the
 *    Pelican-side work has either started or completed.
 *  - status enum extended with 'provisioning' and 'provisioning_failed' so the
 *    /admin/servers list reflects mid-flight + terminal-failure states.
 *  - `provisioning_error` : last error message when status is provisioning_failed,
 *    surfaced in the admin list for debugging.
 *
 * The status column is dropped + recreated because SQLite (used in tests) can't
 * ALTER TABLE on enum columns. MySQL would tolerate `change()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->unique()->after('payment_intent_id');
            $table->text('provisioning_error')->nullable()->after('idempotency_key');
        });

        // Snapshot existing values, drop and recreate the column with the
        // expanded value set. ENUM ALTER is fragile across MySQL/SQLite.
        $existing = DB::table('servers')->pluck('status', 'id');

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->enum('status', [
                'provisioning', 'active', 'suspended', 'terminated',
                'running', 'stopped', 'offline', 'provisioning_failed',
            ])->default('active')->after('name');
        });

        foreach ($existing as $id => $value) {
            DB::table('servers')->where('id', $id)->update(['status' => $value]);
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'provisioning_error']);
        });

        $existing = DB::table('servers')->pluck('status', 'id');

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended', 'terminated', 'running', 'stopped', 'offline'])
                ->default('active')->after('name');
        });

        foreach ($existing as $id => $value) {
            // Coerce new states to a safe legacy value before restore.
            $safe = in_array($value, ['provisioning', 'provisioning_failed'], true) ? 'active' : $value;
            DB::table('servers')->where('id', $id)->update(['status' => $safe]);
        }
    }
};
