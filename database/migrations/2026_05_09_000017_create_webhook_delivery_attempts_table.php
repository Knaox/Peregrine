<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — `WebhookDeliveryAttempt` is the immutable per-retry record.
 * Stores the request body that was actually sent (for forensic replay),
 * the response status + headers + body (truncated to 8 KB), and the
 * latency observed. Retained per delivery — can be purged by an admin
 * cron after N days if the volume becomes a concern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_delivery_id')
                ->constrained('webhook_deliveries')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->longText('request_body');
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->json('response_headers')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('error_type', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->useCurrent();

            $table->index('webhook_delivery_id');
            $table->index('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_attempts');
    }
};
