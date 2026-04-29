<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Auth\AuthProviderRegistry\EnabledProvidersList;
use App\Services\Auth\AuthProviderRegistry\SocialiteConfigurator;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for which OAuth providers are enabled and how they
 * are configured at runtime.
 *
 * Why this exists:
 *   - Provider client_id/secret live in the settings table (admin-editable,
 *     encrypted at rest), NOT in config/services.php. Before we can use
 *     Laravel Socialite, we have to inject the config into config('services.*')
 *     at request time. This class centralises that runtime config push.
 *   - The Filament AuthSettings page needs to know how many users would be
 *     locked out if an admin disables a provider (plan §S8). That SQL lives
 *     here, not duplicated in the form schema.
 *   - The /api/auth/providers endpoint needs a secrets-free view of the
 *     enabled providers for the frontend login buttons. That view is here.
 */
class AuthProviderRegistry
{
    /** Providers we know how to drive. Shop + Paymenter + WHMCS are canonical (custom drivers). */
    private const SUPPORTED = ['shop', 'google', 'discord', 'linkedin', 'paymenter', 'whmcs'];

    /** Socialite core uses `linkedin-openid` for the modern OIDC flow. */
    private const SOCIALITE_DRIVER = [
        'shop' => 'shop',
        'google' => 'google',
        'discord' => 'discord',
        'linkedin' => 'linkedin-openid',
        'paymenter' => 'paymenter',
        'whmcs' => 'whmcs',
    ];

    /**
     * Canonical providers act as primary identity sources: they auto-create
     * local accounts on first login, sync the user's email to Pelican on every
     * callback, and may surface a register URL on the login page. Only ONE
     * canonical provider can be active at a time (Filament save() enforces).
     * Order = priority when several are accidentally enabled (defensive fallback).
     */
    private const CANONICAL_PROVIDERS = ['shop', 'paymenter', 'whmcs'];

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function isSupported(string $provider): bool
    {
        return in_array($provider, self::SUPPORTED, true);
    }

    public function isEnabled(string $provider): bool
    {
        if (! $this->isSupported($provider)) {
            return false;
        }

        if ($provider === 'shop') {
            return $this->settings->get('auth_shop_enabled', 'false') === 'true';
        }

        if ($provider === 'paymenter') {
            return $this->settings->get('auth_paymenter_enabled', 'false') === 'true';
        }

        if ($provider === 'whmcs') {
            return $this->settings->get('auth_whmcs_enabled', 'false') === 'true';
        }

        $providers = $this->decodeProviders();

        return (bool) ($providers[$provider]['enabled'] ?? false);
    }

    /**
     * Returns the currently active canonical provider id, or null when none is
     * enabled. If both are enabled (shouldn't happen — Filament save() blocks),
     * returns the first in declaration order (shop wins) and logs a warning.
     */
    public function canonicalProvider(): ?string
    {
        $active = array_values(array_filter(
            self::CANONICAL_PROVIDERS,
            fn (string $p): bool => $this->isEnabled($p),
        ));

        if (count($active) > 1) {
            \Illuminate\Support\Facades\Log::warning('Multiple canonical IdPs enabled simultaneously; falling back to first', [
                'active' => $active,
                'fallback' => $active[0],
            ]);
        }

        return $active[0] ?? null;
    }

    public function isCanonical(string $provider): bool
    {
        return in_array($provider, self::CANONICAL_PROVIDERS, true) && $this->isEnabled($provider);
    }

    /**
     * The authoritative Socialite driver id for a given logical provider id
     * (LinkedIn → linkedin-openid). Throws ProviderDisabledException when the
     * caller asks for an unsupported provider.
     */
    public function socialiteDriver(string $provider): string
    {
        if (! $this->isSupported($provider)) {
            throw new \App\Exceptions\Auth\ProviderDisabledException();
        }

        return self::SOCIALITE_DRIVER[$provider];
    }

    /**
     * Returns the provider descriptor list for the frontend. Secrets never
     * leak through this method — only id, label key, enabled status, and
     * the redirect URL (the same one the admin configured on Google/Discord
     * Developer Consoles).
     *
     * @return list<array{id: string, enabled: bool, redirect_url: string, canonical: bool, logo_url: ?string}>
     */
    public function enabledProviders(): array
    {
        return EnabledProvidersList::build($this);
    }

    /**
     * Pushes provider config into config('services.*') at runtime so
     * Laravel Socialite picks it up on the next `driver()` call. Also
     * decrypts the client_secret from its stored envelope.
     *
     * Body lives in `AuthProviderRegistry/SocialiteConfigurator` to keep
     * this class under the 300-line file rule.
     */
    public function configureSocialite(string $provider): void
    {
        if (! $this->isEnabled($provider)) {
            throw new \App\Exceptions\Auth\ProviderDisabledException();
        }

        SocialiteConfigurator::apply($this, $provider);
    }

    /**
     * Plan §S8: count users who would lose access if the given provider were
     * disabled — users with no password AND no other linked identity.
     */
    public function providerHasExclusiveUsers(string $provider): int
    {
        if (! $this->isSupported($provider)) {
            return 0;
        }

        return DB::table('users as u')
            // Wrap the "no password" branch in a closure — otherwise the
            // orWhere bleeds across whereExists/whereNotExists and lets rows
            // with a password slip through (plan §S7 / §S8).
            ->where(function ($q): void {
                $q->whereNull('u.password')->orWhere('u.password', '');
            })
            ->whereExists(function ($q) use ($provider): void {
                $q->select(DB::raw(1))
                    ->from('oauth_identities as oi1')
                    ->whereColumn('oi1.user_id', 'u.id')
                    ->where('oi1.provider', $provider);
            })
            ->whereNotExists(function ($q) use ($provider): void {
                $q->select(DB::raw(1))
                    ->from('oauth_identities as oi2')
                    ->whereColumn('oi2.user_id', 'u.id')
                    ->where('oi2.provider', '<>', $provider);
            })
            ->distinct()
            ->count('u.id');
    }

    /**
     * Persist an updated client_secret — encrypted. Used by the Filament
     * AuthSettings save() when an admin types a new secret. Keeps secret
     * handling out of the form schema + SettingsService call sites.
     */
    public function storeShopClientSecret(string $plaintext): void
    {
        $cfg = $this->shopConfig();
        $cfg['client_secret_encrypted'] = $plaintext === '' ? '' : Crypt::encryptString($plaintext);
        $this->settings->set('auth_shop_config', json_encode($cfg, JSON_THROW_ON_ERROR));
    }

    public function storePaymenterClientSecret(string $plaintext): void
    {
        $cfg = $this->paymenterConfig();
        $cfg['client_secret_encrypted'] = $plaintext === '' ? '' : Crypt::encryptString($plaintext);
        $this->settings->set('auth_paymenter_config', json_encode($cfg, JSON_THROW_ON_ERROR));
    }

    public function storeWhmcsClientSecret(string $plaintext): void
    {
        $cfg = $this->whmcsConfig();
        $cfg['client_secret_encrypted'] = $plaintext === '' ? '' : Crypt::encryptString($plaintext);
        $this->settings->set('auth_whmcs_config', json_encode($cfg, JSON_THROW_ON_ERROR));
    }

    public function storeProviderClientSecret(string $provider, string $plaintext): void
    {
        $providers = $this->decodeProviders();
        $providers[$provider] ??= [];
        $providers[$provider]['client_secret_encrypted'] = $plaintext === '' ? '' : Crypt::encryptString($plaintext);
        $this->settings->set('auth_providers', json_encode($providers, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function shopConfig(): array
    {
        $raw = (string) $this->settings->get('auth_shop_config', '{}');

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function paymenterConfig(): array
    {
        $raw = (string) $this->settings->get('auth_paymenter_config', '{}');

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function whmcsConfig(): array
    {
        $raw = (string) $this->settings->get('auth_whmcs_config', '{}');

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Active canonical config (whichever of shop/paymenter/whmcs is currently
     * enabled). Returns an empty array when none is active.
     *
     * @return array<string, mixed>
     */
    public function canonicalConfig(): array
    {
        return match ($this->canonicalProvider()) {
            'shop' => $this->shopConfig(),
            'paymenter' => $this->paymenterConfig(),
            'whmcs' => $this->whmcsConfig(),
            default => [],
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function decodeProviders(): array
    {
        $raw = (string) $this->settings->get('auth_providers', '{}');

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function defaultRedirect(string $provider): string
    {
        return rtrim((string) config('app.url', ''), '/')."/api/auth/social/{$provider}/callback";
    }

    private function decryptSecret(string $envelope): string
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
}
