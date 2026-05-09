<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Each Server can carry an opaque shop-side reference identifying
 * the order/cart on the third-party shop's end. Sourced from
 * `metadata.peregrine_external_order_id` on the inbound Stripe event ;
 * exposed back to the shop via `GET /api/v1/orders/{externalOrderId}`.
 *
 * Indexed (non-unique). Two different shops MAY happen to use the same
 * string — uniqueness is per-shop, enforced at the application layer (the
 * lookup endpoint is scoped to the requesting shop).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('external_order_id')->nullable()->after('idempotency_key');
            $table->index('external_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['external_order_id']);
            $table->dropColumn('external_order_id');
        });
    }
};
