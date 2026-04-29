<?php

namespace App\Services\Settings;

use App\Services\SettingsService;
use App\Services\SetupService;

/**
 * Maps a Filament Settings page form payload to its persistence layer:
 *   - branding / appearance / locale / proxies / debug / timezone → DB (settings table)
 *   - Pelican URLs + keys / SMTP config → .env (via SetupService::writeEnv)
 *
 * Extracted from `App\Filament\Pages\Settings::save()` to keep that page
 * file under the 300-line plafond CLAUDE.md and to make the persistence
 * mapping testable in isolation.
 */
final class SettingsPersister
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SetupService $setup,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function persist(array $data): void
    {
        $this->persistAppearance($data);
        $this->persistLogos($data);
        $envValues = $this->collectEnvUpdates($data);
        $this->persistRuntimeFlags($data);

        if (! empty($envValues)) {
            $this->setup->writeEnv($envValues);
        }

        $this->settings->clearCache();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistAppearance(array $data): void
    {
        $this->settings->set('app_name', $data['app_name'] ?? null);
        $this->settings->set('show_app_name', ($data['show_app_name'] ?? true) ? 'true' : 'false');
        $this->settings->set('logo_height', $data['logo_height'] ?? '40');

        $defaultLocale = in_array($data['default_locale'] ?? 'en', ['en', 'fr'], true)
            ? $data['default_locale']
            : 'en';
        $this->settings->set('default_locale', $defaultLocale);

        $this->settings->set('header_links', json_encode($data['header_links'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistLogos(array $data): void
    {
        $logoValue = $data['logo_url'] ?? null;
        if ($logoValue) {
            $path = is_array($logoValue) ? (array_values($logoValue)[0] ?? null) : $logoValue;
            if ($path) {
                $this->settings->set('app_logo_path', $path);
            }
        }

        // Light-mode logo: optional; empty/cleared value persists as '' so
        // the frontend falls back to the main logo.
        $logoLightValue = $data['logo_url_light'] ?? null;
        $logoLightPath = is_array($logoLightValue) ? (array_values($logoLightValue)[0] ?? null) : $logoLightValue;
        $this->settings->set('app_logo_path_light', $logoLightPath ?: '');

        $faviconValue = $data['favicon_url'] ?? null;
        if ($faviconValue) {
            $path = is_array($faviconValue) ? (array_values($faviconValue)[0] ?? null) : $faviconValue;
            if ($path) {
                $this->settings->set('app_favicon_path', $path);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function collectEnvUpdates(array $data): array
    {
        $envValues = [];
        if (isset($data['pelican_url'])) {
            $envValues['PELICAN_URL'] = $data['pelican_url'];
        }
        if (isset($data['pelican_admin_api_key']) && $data['pelican_admin_api_key'] !== '') {
            $envValues['PELICAN_ADMIN_API_KEY'] = $data['pelican_admin_api_key'];
        }
        if (isset($data['pelican_client_api_key']) && $data['pelican_client_api_key'] !== '') {
            $envValues['PELICAN_CLIENT_API_KEY'] = $data['pelican_client_api_key'];
        }

        $envValues['MAIL_MAILER'] = $data['mail_mailer'] ?? 'smtp';
        if (($data['mail_mailer'] ?? 'smtp') === 'smtp') {
            $envValues['MAIL_HOST'] = $data['mail_host'] ?? '';
            $envValues['MAIL_PORT'] = $data['mail_port'] ?? '587';
            $envValues['MAIL_ENCRYPTION'] = $data['mail_encryption'] ?? 'tls';
            $envValues['MAIL_USERNAME'] = $data['mail_username'] ?? '';
            if (! empty($data['mail_password'])) {
                $envValues['MAIL_PASSWORD'] = $data['mail_password'];
            }
        }
        $envValues['MAIL_FROM_ADDRESS'] = $data['mail_from_address'] ?? '';
        $envValues['MAIL_FROM_NAME'] = $data['mail_from_name'] ?? config('app.name', 'Peregrine');

        return $envValues;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistRuntimeFlags(array $data): void
    {
        // Developer / General / Network → DB (table `settings`) instead of
        // .env, so a Docker stack rebuild doesn't wipe them. AppServiceProvider
        // overrides config('app.debug') and config('app.timezone') from these
        // settings at boot ; DynamicTrustProxies middleware reads
        // 'trusted_proxies' on every request.
        $this->settings->set('app_debug', ($data['app_debug'] ?? false) ? 'true' : 'false');
        $this->settings->set('app_timezone', (string) ($data['app_timezone'] ?? 'UTC'));

        $proxies = $data['trusted_proxies'] ?? [];
        $proxies = is_array($proxies)
            ? array_values(array_filter(array_map('trim', $proxies), fn ($v): bool => $v !== ''))
            : [];
        $this->settings->set('trusted_proxies', empty($proxies) ? '*' : implode(',', $proxies));
    }
}
