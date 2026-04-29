<?php

namespace App\Services\Auth\AuthProviderRegistry;

use App\Services\Auth\AuthProviderRegistry;

/**
 * Builds the secrets-free descriptor list returned by `/api/auth/providers`
 * (consumed by the React login page to render the SSO buttons).
 *
 * Extracted from `AuthProviderRegistry` to keep the registry under the
 * 300-line file budget. Called via `AuthProviderRegistry::enabledProviders()`
 * — the registry stays the single public entry point for callers.
 */
final class EnabledProvidersList
{
    /**
     * @return list<array{id: string, enabled: bool, redirect_url: string, canonical: bool, logo_url: ?string}>
     */
    public static function build(AuthProviderRegistry $registry): array
    {
        $out = [];

        if ($registry->isEnabled('shop')) {
            $shop = $registry->shopConfig();
            $out[] = [
                'id' => 'shop',
                'enabled' => true,
                'redirect_url' => (string) ($shop['redirect_uri'] ?? self::defaultRedirect('shop')),
                'canonical' => true,
                'logo_url' => self::logoUrl((string) ($shop['logo_path'] ?? '')),
            ];
        }

        if ($registry->isEnabled('paymenter')) {
            $pm = $registry->paymenterConfig();
            $out[] = [
                'id' => 'paymenter',
                'enabled' => true,
                'redirect_url' => (string) ($pm['redirect_uri'] ?? self::defaultRedirect('paymenter')),
                'canonical' => true,
                'logo_url' => self::logoUrl((string) ($pm['logo_path'] ?? '')),
            ];
        }

        if ($registry->isEnabled('whmcs')) {
            $wh = $registry->whmcsConfig();
            $out[] = [
                'id' => 'whmcs',
                'enabled' => true,
                'redirect_url' => (string) ($wh['redirect_uri'] ?? self::defaultRedirect('whmcs')),
                'canonical' => true,
                'logo_url' => self::logoUrl((string) ($wh['logo_path'] ?? '')),
            ];
        }

        $providers = $registry->decodeProviders();
        foreach (['google', 'discord', 'linkedin'] as $id) {
            if (! ($providers[$id]['enabled'] ?? false)) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'enabled' => true,
                'redirect_url' => (string) ($providers[$id]['redirect_uri'] ?? self::defaultRedirect($id)),
                'canonical' => false,
                'logo_url' => self::logoUrl((string) ($providers[$id]['logo_path'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Resolve a stored relative path (e.g. "branding/oauth/xyz.png") to the
     * absolute URL the frontend can drop into an <img src>. Returns null when
     * no custom logo is configured — the SPA falls back to the default SVG.
     */
    private static function logoUrl(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    private static function defaultRedirect(string $provider): string
    {
        return rtrim((string) config('app.url', ''), '/')."/api/auth/social/{$provider}/callback";
    }
}
