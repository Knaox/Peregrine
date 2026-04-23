<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency ledger for Pelican outgoing webhooks (Bridge Paymenter mode).
 *
 * Pelican does NOT provide a native event ID and does NOT retry failed
 * deliveries. We derive an idempotency hash from
 *   sha256(event_type|model_id|updated_at|body_hash)
 * so that a Pelican panel re-sending the same physical event (e.g. when an
 * admin re-enables the webhook config) doesn't produce duplicate side
 * effects on Peregrine.
 *
 * Retention is short — 24h is enough for debug, no replay window to honour
 * because Pelican never retries on its own. The artisan command
 * `pelican:clean-processed-events` purges older rows daily.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelican_processed_events', function (Blueprint $table) {
            // sha256 hex digest = 64 chars, used as the natural primary key.
            $table->string('idempotency_hash', 64)->primary();
            $table->string('event_type')->index();
            $table->unsignedBigInteger('pelican_model_id')->index();
            $table->json('payload_summary')->nullable();
            $table->unsignedSmallInteger('response_status');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelican_processed_events');
    }
};
