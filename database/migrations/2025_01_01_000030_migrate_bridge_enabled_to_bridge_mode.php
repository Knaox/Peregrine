<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Translates the legacy `bridge_enabled` boolean setting into the new
 * `bridge_mode` enum (Disabled / ShopStripe / Paymenter).
 *
 * Idempotent: only writes `bridge_mode` if it doesn't already exist. The
 * legacy `bridge_enabled` row is left in place — BridgeModeService still
 * reads it as a fallback for one release, then a follow-up cleanup migration
 * can drop it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('settings')->where('key', 'bridge_mode')->value('value');
        if ($existing !== null) {
            return;
        }

        $wasEnabled = DB::table('settings')->where('key', 'bridge_enabled')->value('value') === 'true';

        DB::table('settings')->updateOrInsert(
            ['key' => 'bridge_mode'],
            [
                'value' => $wasEnabled ? 'shop_stripe' : 'disabled',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'bridge_mode')->delete();
    }
};
