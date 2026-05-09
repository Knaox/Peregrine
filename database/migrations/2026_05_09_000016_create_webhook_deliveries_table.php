<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — `WebhookDelivery` is the per-endpoint shipping record for a
 * `WebhookEvent`. Each delivery aggregates N retry attempts (see
 * `webhook_delivery_attempts`) and tracks the latest known status.
 *
 * `next_retry_at` is set when a delivery fails and the dispatcher
 * schedules another attempt ; cleared on success or terminal expiry.
 * The composite index on (status, next_retry_at) lets the Filament
 * audit page query "deliveries due for retry now" efficiently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')
                ->constrained('webhook_endpoints')
                ->cascadeOnDelete();
            $table->foreignId('webhook_event_id')
                ->constrained('webhook_events')
                ->cascadeOnDelete();
            $table->enum('status', ['pending', 'success', 'failed', 'expired'])->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('first_attempted_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->index('webhook_endpoint_id');
            $table->index('webhook_event_id');
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
