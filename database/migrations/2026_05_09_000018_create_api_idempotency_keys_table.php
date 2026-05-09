<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Persisted idempotency cache for `/api/v1/*` POST endpoints.
 * Receives `Idempotency-Key` header values and stores the response so
 * repeated requests with the same key return the same body without
 * re-executing side effects.
 *
 * Scoped per-shop via UNIQUE(shop_id, key) — two shops are free to use
 * identical key strings without colliding. Rows expire 24h after
 * creation (cron purge / lazy clean on lookup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('key');
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->longText('response_body');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['shop_id', 'key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
    }
};
