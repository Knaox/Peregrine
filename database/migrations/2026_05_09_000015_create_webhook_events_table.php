<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — `WebhookEvent` is an immutable record of "something
 * dispatchable happened" inside Peregrine. One event row, N delivery
 * rows (one per subscribed endpoint of an authorised shop).
 *
 * `idempotency_key` (UUID v7 — sortable) is exposed verbatim as the
 * Standard Webhooks `webhook-id` header so receivers can dedupe their
 * end. UNIQUE on (event_type, aggregate_type, aggregate_id, idempotency_key)
 * is unnecessary because UUID v7 is universally unique on its own.
 *
 * `processed_at` flips the moment the fan-out has scheduled all
 * deliveries — independent of whether deliveries succeeded. Failures
 * are tracked at the delivery layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100);
            $table->string('idempotency_key', 64)->unique();
            $table->string('aggregate_type', 100);
            $table->unsignedBigInteger('aggregate_id');
            $table->json('payload');
            $table->timestamp('emitted_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('event_type');
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index('emitted_at');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
