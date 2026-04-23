<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for every Bridge API call received from the Shop.
 *
 * Indexable by shop_plan_id (debug a specific plan's sync history) and by
 * attempted_at (recent activity dashboard). Pas d'event/listener — c'est une
 * action HTTP déjà validée par le middleware HMAC, le controller insère
 * directement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bridge_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');                       // 'upsert' | 'delete'
            $table->unsignedBigInteger('shop_plan_id')->nullable();
            $table->foreignId('server_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status');
            $table->text('response_body')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('signature_valid');
            $table->timestamp('attempted_at');
            $table->index('shop_plan_id');
            $table->index('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bridge_sync_logs');
    }
};
