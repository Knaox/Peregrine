<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Pivot N:N between `Shop` and `ServerConfiguration`.
 *
 * A given technical configuration can be sold by multiple shops in
 * parallel ; conversely a shop can offer multiple configurations. Each
 * pivot row carries shop-specific metadata :
 *
 *  - `shop_external_id` : the shop's own internal identifier for this
 *    configuration (e.g. "plan-mc-pro"). Stored for cross-system audit ;
 *    Stripe metadata's `peregrine_external_order_id` is per-order, not
 *    per-config.
 *  - `is_visible` : per-shop toggle without removing the link. Lets an
 *    admin disable the offering temporarily without losing the pivot.
 *  - `sort_order` : per-shop display order in the public API listing.
 *
 * UNIQUE(shop_id, server_configuration_id) blocks the same shop attaching
 * a configuration twice. Both FKs cascade on delete so an orphaned shop
 * or configuration cleans its pivot rows automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_server_configuration', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnDelete();
            $table->foreignId('server_configuration_id')
                ->constrained('server_configurations')
                ->cascadeOnDelete();
            $table->string('shop_external_id')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['shop_id', 'server_configuration_id'],
                'shop_server_configuration_unique'
            );
            $table->index('shop_id');
            $table->index('server_configuration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_server_configuration');
    }
};
