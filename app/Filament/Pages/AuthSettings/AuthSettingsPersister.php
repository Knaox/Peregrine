<?php

namespace App\Filament\Pages\AuthSettings;

use App\Services\Auth\AuthProviderRegistry;
use App\Services\SettingsService;

/**
 * Writes form data from `AuthSettings` admin page to the `settings` table.
 *
 * Extracted from the Page itself to honour the 300-line file rule. Each
 * persist* method takes the form `$data` array + the registry instance,
 * mutates the registry's stored config, and writes the JSON envelope back
 * to the settings store.
 *
 * Read flow stays in the Page (mount() reads from registry directly), only
 * the write flow lives here. Same pattern as the FormSchema sibling — see
 * `AuthSettingsFormSchema`.
 */
final class AuthSettingsPersister
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function persistShop(array $data, AuthProviderRegistry $registry, string $defaultRedirect): void
    {
        $existing = $registry->shopConfig();
        $existing['client_id'] = (string) ($data['auth_shop_client_id'] ?? '');
        $existing['authorize_url'] = (string) ($data['auth_shop_authorize_url'] ?? '');
        $existing['token_url'] = (string) ($data['auth_shop_token_url'] ?? '');
        $existing['user_url'] = (string) ($data['auth_shop_user_url'] ?? '');
        $existing['register_url'] = (string) ($data['auth_shop_register_url'] ?? '');
        $existing['redirect_uri'] = $existing['redirect_uri'] ?? $defaultRedirect;

        // FileUpload returns an array (or cleared null) — extract first path.
        $logoValue = $data['auth_shop_logo_path'] ?? null;
        $logoPath = is_array($logoValue) ? (array_values($logoValue)[0] ?? null) : $logoValue;
        $existing['logo_path'] = $logoPath ?: '';

        $settings = app(SettingsService::class);
        $settings->set('auth_shop_config', json_encode($existing, JSON_THROW_ON_ERROR));
        $settings->set('auth_shop_enabled', ($data['auth_shop_enabled'] ?? false) ? 'true' : 'false');

        $typed = (string) ($data['auth_shop_client_secret'] ?? '');
        if ($typed !== '') {
            $registry->storeShopClientSecret($typed);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function persistPaymenter(array $data, AuthProviderRegistry $registry, string $defaultRedirect): void
    {
        $existing = $registry->paymenterConfig();
        $existing['base_url'] = rtrim((string) ($data['auth_paymenter_base_url'] ?? ''), '/');
        $existing['client_id'] = (string) ($data['auth_paymenter_client_id'] ?? '');
        $existing['register_url'] = (string) ($data['auth_paymenter_register_url'] ?? '');
        $existing['redirect_uri'] = $existing['redirect_uri'] ?? $defaultRedirect;

        $logoValue = $data['auth_paymenter_logo_path'] ?? null;
        $logoPath = is_array($logoValue) ? (array_values($logoValue)[0] ?? null) : $logoValue;
        $existing['logo_path'] = $logoPath ?: '';

        $settings = app(SettingsService::class);
        $settings->set('auth_paymenter_config', json_encode($existing, JSON_THROW_ON_ERROR));
        $settings->set('auth_paymenter_enabled', ($data['auth_paymenter_enabled'] ?? false) ? 'true' : 'false');

        $typed = (string) ($data['auth_paymenter_client_secret'] ?? '');
        if ($typed !== '') {
            $registry->storePaymenterClientSecret($typed);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $defaultRedirects  per-provider redirect URIs
     */
    public static function persistSocialProviders(array $data, AuthProviderRegistry $registry, array $defaultRedirects): void
    {
        $existing = $registry->decodeProviders();

        foreach (['google', 'discord', 'linkedin'] as $id) {
            $existing[$id] ??= [];
            $existing[$id]['enabled'] = (bool) ($data["auth_providers_{$id}_enabled"] ?? false);
            $existing[$id]['client_id'] = (string) ($data["auth_providers_{$id}_client_id"] ?? '');
            $existing[$id]['redirect_uri'] = $existing[$id]['redirect_uri'] ?? ($defaultRedirects[$id] ?? '');
        }

        app(SettingsService::class)->set('auth_providers', json_encode($existing, JSON_THROW_ON_ERROR));

        foreach (['google', 'discord', 'linkedin'] as $id) {
            $typed = (string) ($data["auth_providers_{$id}_client_secret"] ?? '');
            if ($typed !== '') {
                $registry->storeProviderClientSecret($id, $typed);
            }
        }
    }

    /**
     * Read the previously-saved enabled state for a provider id. Used by
     * the S8 lockout guard ("disabling a provider with exclusive users").
     */
    public static function wasPreviouslyEnabled(string $providerId): bool
    {
        if ($providerId === 'shop') {
            return app(SettingsService::class)->get('auth_shop_enabled', 'false') === 'true';
        }

        if ($providerId === 'paymenter') {
            return app(SettingsService::class)->get('auth_paymenter_enabled', 'false') === 'true';
        }

        $providers = app(AuthProviderRegistry::class)->decodeProviders();

        return (bool) ($providers[$providerId]['enabled'] ?? false);
    }
}
