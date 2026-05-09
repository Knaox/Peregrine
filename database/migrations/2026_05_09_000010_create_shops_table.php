<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Multi-shop registry. A `Shop` is the third-party (or internal
 * future) reseller of `ServerConfiguration` rows, identified by an admin
 * who minted at least one API key.
 *
 * `slug` is the stable string used in admin URLs and for cross-system
 * references — uniqueness enforced at the schema layer to block conflicts
 * before runtime. `domain` is informational (no auth derived from it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
