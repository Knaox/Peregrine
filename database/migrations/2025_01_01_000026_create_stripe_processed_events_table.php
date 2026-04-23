<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency ledger for Stripe webhook events.
 *
 * Stripe retries an event for up to 3 days if our endpoint doesn't 200,
 * and may also occasionally re-deliver an event after a 200 (duplicate
 * delivery). The webhook controller checks this table before dispatching
 * any job — if the event_id is present, the request short-circuits with
 * 200 and no side effect.
 *
 * Cleanup is handled by the daily artisan command
 * `stripe:clean-processed-events` which deletes rows older than 30 days
 * (well past Stripe's retry window).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_processed_events', function (Blueprint $table) {
            $table->string('event_id')->primary();              // evt_xxx from Stripe
            $table->string('event_type')->index();              // checkout.session.completed, …
            $table->json('payload_summary')->nullable();        // small subset for debug
            $table->unsignedSmallInteger('response_status');    // 200/400/500/…
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_processed_events');
    }
};
