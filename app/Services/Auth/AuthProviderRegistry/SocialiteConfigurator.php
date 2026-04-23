<?php

namespace App\Services\Auth\AuthProviderRegistry;

use App\Services\Auth\AuthProviderRegistry;
use Illuminate\Support\Facades\Crypt;

/**
 * Pushes the runtime OAuth provider config into Laravel's `config('services.*')`
 * tree so Socialite picks it up on the next `driver()` call.
 *
 * Extracted from `AuthProviderRegistry` to keep the registry under the
 * 300-line file budget. Called via `AuthProviderRegistry::configureSocialite()`
 * — the registry stays the single public entry point for callers (no call
 * site needs to change).
 */
final class SocialiteConfigurator
{
    public static function apply(AuthProviderRegistry $registry, string $provider): void
    {
        if ($provider === 'shop') {
            self::applyShop($registry);
            return;
        }

        if ($provider === 'paymenter') {
            self::applyPaymenter($registry);
            return;
        }

        self::applyGenericSocialProvider($registry, $provider);
    }

    private static function applyShop(AuthProviderRegistry $registry): void
    {
        $cfg = $registry->shopConfig();
        config()->set('services.shop', [
            'client_id' => (string) ($cfg['client_id'] ?? ''),
            'client_secret' => self::decryptSecret((string) ($cfg['client_secret_encrypted'] ?? '')),
            'redirect' => (string) ($cfg['redirect_uri'] ?? self::defaultRedirect('shop')),
            'authorize_url' => (string) ($cfg['authorize_url'] ?? ''),
            'token_url' => (string) ($cfg['token_url'] ?? ''),
            'user_url' => (string) ($cfg['user_url'] ?? ''),
        ]);
    }

    private static function applyPaymenter(AuthProviderRegistry $registry): void
    {
        $cfg = $registry->paymenterConfig();
        $base = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        config()->set('services.paymenter', [
            'client_id' => (string) ($cfg['client_id'] ?? ''),
            'client_secret' => self::decryptSecret((string) ($cfg['client_secret_encrypted'] ?? '')),
            'redirect' => (string) ($cfg['redirect_uri'] ?? self::defaultRedirect('paymenter')),
            'base_url' => $base,
            'authorize_url' => $base.'/oauth/authorize',
            'token_url' => $base.'/api/oauth/token',
            'user_url' => $base.'/api/me',
        ]);
    }

    private static function applyGenericSocialProvider(AuthProviderRegistry $registry, string $provider): void
    {
        $providers = $registry->decodeProviders();
        $p = $providers[$provider] ?? [];

        config()->set("services.{$registry->socialiteDriver($provider)}", [
            'client_id' => (string) ($p['client_id'] ?? ''),
            'client_secret' => self::decryptSecret((string) ($p['client_secret_encrypted'] ?? '')),
            'redirect' => (string) ($p['redirect_uri'] ?? self::defaultRedirect($provider)),
        ]);
    }

    private static function decryptSecret(string $envelope): string
    {
        if ($envelope === '') {
            return '';
        }
        try {
            return Crypt::decryptString($envelope);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function defaultRedirect(string $provider): string
    {
        return rtrim((string) config('app.url', ''), '/')."/api/auth/social/{$provider}/callback";
    }
}
