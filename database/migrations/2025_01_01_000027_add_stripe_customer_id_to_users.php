<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `stripe_customer_id` column was already added on migration
 * `2025_01_01_000001_modify_users_table_for_peregrine.php` (line 18) but
 * without a UNIQUE constraint. Cardinality is strictly 1:1 — a single
 * Peregrine user maps to a single Stripe customer (who can hold N
 * subscriptions = N Servers, but always one customer per user).
 *
 * This migration just promotes the column to UNIQUE so the webhook
 * handler can safely upsert by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
        });
    }
};
