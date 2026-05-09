<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — API keys minted for a `Shop`. Plaintext token is shown ONCE on
 * creation (Filament modal) and never persisted ; only its SHA-256 hash is
 * stored, paired with the readable prefix (`psk_live_` / `psk_test_`) and
 * the last 4 chars for admin-side display.
 *
 * `abilities` is a JSON array of scope strings (e.g.
 * `["configurations:read","webhooks:write"]`). Empty array = no scopes.
 *
 * `revoked_at` is a soft revocation marker — middleware rejects any key
 * whose `revoked_at` is non-null. Allows audit trail of revoked keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnDelete();
            $table->string('label');
            $table->string('key_prefix', 16);
            $table->char('key_hash', 64)->unique();
            $table->char('key_last4', 4);
            $table->json('abilities')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('shop_id');
            $table->index('revoked_at');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_api_keys');
    }
};
