<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Extracts the Pelican webhook config from the Bridge module into a
 * standalone feature.
 *
 * What changes :
 *   - `bridge_pelican_webhook_token` (legacy, Bridge-coupled, Paymenter-only)
 *     → `pelican_webhook_token`     (standalone, encrypted, mode-agnostic)
 *   - new `pelican_webhook_enabled` boolean (default 'true' if a token was
 *     already configured, 'false' otherwise — preserves existing setups)
 *
 * Idempotent: skips the rename if `pelican_webhook_token` already exists.
 * The legacy row is kept for one release as a fallback (VerifyPelicanWebhookToken
 * reads it if the new key is missing). A follow-up cleanup migration can drop it.
 *
 * Cache is flushed for both the old and new keys — SettingsService caches
 * for 1h, so without the flush the middleware would read the stale encrypted
 * value (or null) from cache for up to an hour after deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $alreadyMigrated = DB::table('settings')->where('key', 'pelican_webhook_token')->exists();

        if (! $alreadyMigrated) {
            $legacyToken = DB::table('settings')
                ->where('key', 'bridge_pelican_webhook_token')
                ->value('value');

            if ($legacyToken !== null && $legacyToken !== '') {
                DB::table('settings')->insert([
                    'key' => 'pelican_webhook_token',
                    'value' => $legacyToken,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! DB::table('settings')->where('key', 'pelican_webhook_enabled')->exists()) {
            // Default the new toggle ON if a token was already configured —
            // existing Paymenter installs keep working without admin action.
            $hasToken = DB::table('settings')
                    ->whereIn('key', ['pelican_webhook_token', 'bridge_pelican_webhook_token'])
                    ->whereNotNull('value')
                    ->where('value', '!=', '')
                    ->exists();

            DB::table('settings')->insert([
                'key' => 'pelican_webhook_enabled',
                'value' => $hasToken ? 'true' : 'false',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->flushSettingsCache([
            'pelican_webhook_token',
            'pelican_webhook_enabled',
            'bridge_pelican_webhook_token',
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'pelican_webhook_token')->delete();
        DB::table('settings')->where('key', 'pelican_webhook_enabled')->delete();

        $this->flushSettingsCache([
            'pelican_webhook_token',
            'pelican_webhook_enabled',
            'bridge_pelican_webhook_token',
        ]);
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function flushSettingsCache(array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget('settings.'.$key);
        }
    }
};
