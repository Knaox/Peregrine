<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — Outbound webhook target. Each `WebhookEndpoint` is a URL the
 * owning `Shop` exposes for Peregrine to push signed events to.
 *
 * `signing_secret` is encrypted at rest via Crypt::encryptString — never
 * stored plaintext (mutator on the model handles the encryption). The
 * shop ALSO holds this secret on its end and uses it to verify the
 * `webhook-signature` header.
 *
 * `subscribed_events` is a JSON list of event types the shop wants
 * delivered. Events not in the list are silently skipped during fan-out.
 *
 * `consecutive_failures` is bumped by the dispatcher and reset on first
 * success — used by the Filament dashboard to surface flaky endpoints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 1024);
            $table->text('signing_secret');
            $table->enum('status', ['active', 'paused', 'disabled'])->default('active');
            $table->json('subscribed_events');
            $table->unsignedSmallInteger('max_retries')->default(5);
            $table->unsignedSmallInteger('timeout_seconds')->default(30);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_delivery_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('shop_id');
            $table->index('status');
            $table->index('last_delivery_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
