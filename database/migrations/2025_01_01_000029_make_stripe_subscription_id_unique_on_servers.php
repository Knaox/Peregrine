<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a UNIQUE index on `servers.stripe_subscription_id` so that a single
 * Stripe subscription always maps to exactly one Server row.
 *
 * Why critical: every `customer.subscription.*` event lookup is
 *   `Server::where('stripe_subscription_id', $id)->first()`. Without this
 * unique constraint, a race during checkout (two `checkout.session.completed`
 * deliveries before the idempotency_key write completes on the local DB)
 * could leave two rows, and `subscription.updated` would touch one at random.
 *
 * Existing nulls are allowed — only non-null values must be unique. MySQL,
 * PostgreSQL and SQLite all support multiple NULLs in a UNIQUE column, so
 * the rare legacy server (synced from Pelican without subscription) is fine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unique('stripe_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropUnique(['stripe_subscription_id']);
        });
    }
};
