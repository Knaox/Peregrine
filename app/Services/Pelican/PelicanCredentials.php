<?php

namespace App\Services\Pelican;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Crypt;

/**
 * Single source of truth for Pelican URL + API keys.
 *
 * Reads from the `settings` table (where the Setup Wizard writes them
 * via SetupController::install). Secrets are stored encrypted via
 * Crypt::encryptString and decrypted on read.
 *
 * Falls back to config() (env) for legacy installs that haven't run the
 * new wizard yet — keeps existing deployments working without a forced
 * migration step. Once the admin saves anything in /admin/settings the
 * settings-table value wins permanently.
 */
class PelicanCredentials
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function url(): string
    {
        $value = (string) $this->settings->get('pelican_url', '');
        if ($value === '') {
            $value = (string) config('panel.pelican.url', '');
        }
        return rtrim($value, '/');
    }

    public function adminApiKey(): string
    {
        return $this->resolveSecret('pelican_admin_api_key', 'panel.pelican.admin_api_key');
    }

    public function clientApiKey(): string
    {
        return $this->resolveSecret('pelican_client_api_key', 'panel.pelican.client_api_key');
    }

    private function resolveSecret(string $settingsKey, string $configKey): string
    {
        $stored = $this->settings->get($settingsKey);
        if ($stored !== null && $stored !== '') {
            try {
                return Crypt::decryptString((string) $stored);
            } catch (\Throwable) {
                // Stored as plaintext (legacy import, manual seed) — pass through.
                return (string) $stored;
            }
        }
        return (string) config($configKey, '');
    }
}
