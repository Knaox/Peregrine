<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class AuthSettingsSeeder extends Seeder
{
    /**
     * Idempotent: only inserts keys that don't already exist. Safe to re-run;
     * admin-edited values are preserved.
     *
     * On first boot after migration, reads fallback values from .env (AUTH_MODE,
     * OAUTH_*) so the panel keeps functioning with its legacy config until the
     * admin opens the new Filament "Auth & Sécurité" page.
     */
    public function run(): void
    {
        $legacyMode = env('AUTH_MODE', 'local');
        $shopEnabled = $legacyMode === 'oauth';

        $defaults = [
            'auth_local_enabled' => $legacyMode === 'local' ? 'true' : 'true',
            'auth_local_registration_enabled' => $shopEnabled ? 'false' : 'true',
            'auth_shop_enabled' => $shopEnabled ? 'true' : 'false',
            'auth_shop_config' => $this->shopConfig(),
            'auth_providers' => $this->defaultProviders(),
            'auth_2fa_enabled' => 'true',
            'auth_2fa_required_admins' => 'false',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }

    private function shopConfig(): string
    {
        $clientSecret = (string) env('OAUTH_CLIENT_SECRET', '');

        return json_encode([
            'client_id' => (string) env('OAUTH_CLIENT_ID', ''),
            'client_secret_encrypted' => $clientSecret !== ''
                ? Crypt::encryptString($clientSecret)
                : '',
            'authorize_url' => (string) env('OAUTH_AUTHORIZE_URL', ''),
            'token_url' => (string) env('OAUTH_TOKEN_URL', ''),
            'user_url' => (string) env('OAUTH_USER_URL', ''),
            'redirect_uri' => rtrim((string) env('APP_URL', ''), '/').'/api/auth/social/shop/callback',
        ], JSON_THROW_ON_ERROR);
    }

    private function defaultProviders(): string
    {
        $baseUrl = rtrim((string) env('APP_URL', ''), '/');

        return json_encode([
            'google' => [
                'enabled' => false,
                'client_id' => '',
                'client_secret_encrypted' => '',
                'redirect_uri' => $baseUrl.'/api/auth/social/google/callback',
            ],
            'discord' => [
                'enabled' => false,
                'client_id' => '',
                'client_secret_encrypted' => '',
                'redirect_uri' => $baseUrl.'/api/auth/social/discord/callback',
            ],
            'linkedin' => [
                'enabled' => false,
                'client_id' => '',
                'client_secret_encrypted' => '',
                'redirect_uri' => $baseUrl.'/api/auth/social/linkedin/callback',
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
