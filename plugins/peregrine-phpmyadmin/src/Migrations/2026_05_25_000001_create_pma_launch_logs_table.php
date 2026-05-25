<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit ledger for the phpMyAdmin plugin: one row per signon-token launch
 * (issued in Peregrine when a user clicks the button) and one per redeem
 * (when phpMyAdmin's SignonScript exchanges the token for credentials).
 *
 * No secrets are stored — only who / which database / when / from where.
 *
 * Run order: standalone (no FK dependencies beyond the implicit user/server
 * ids, intentionally left unconstrained so log rows survive deletions). It is
 * applied through the plugin lifecycle (activation / `plugin:force-resync`),
 * never via plain `php artisan migrate`. Rollback drops the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pma_launch_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('server_id')->nullable();
            $table->string('database_id')->nullable();
            $table->string('event', 16)->default('launch');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pma_launch_logs');
    }
};
