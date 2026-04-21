<?php

namespace App\Services\Auth;

use App\Models\OAuthIdentity;
use App\Models\User;
use App\Services\SettingsService;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Decides what to do with a fresh SocialiteUser returned from a callback.
 * Enforces plan §S1 email verification check before ever auto-linking by
 * email — a malicious actor could otherwise sign up on a provider using
 * someone else's email and hijack the local account.
 *
 * Kept separate from SocialAuthService so the matching logic can be unit
 * tested in isolation (no Event::fake, no Auth::login).
 */
class SocialUserMatcher
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function match(string $provider, SocialiteUser $socialiteUser): MatchResult
    {
        $providerUserId = (string) $socialiteUser->getId();
        $email = strtolower(trim((string) $socialiteUser->getEmail()));

        // 1. Exact identity hit — user previously linked this same provider account.
        $identity = OAuthIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($identity !== null) {
            $user = $identity->user()->first();
            if ($user !== null) {
                // Defense-in-depth: for the Shop (canonical IdP) we revalidate
                // email_verified on every login, not just at link time. A user
                // whose Shop email becomes unverified (manual admin reset,
                // account suspended, etc.) must re-verify before re-entering.
                // Other providers are trusted once linked — the identity row
                // itself is the proof of prior verification.
                if ($provider === 'shop' && ! $this->isEmailVerifiedByProvider($provider, $socialiteUser)) {
                    return new MatchResult(null, MatchResult::ACTION_REJECT_UNVERIFIED_EMAIL);
                }
                return new MatchResult($user, MatchResult::ACTION_MATCH_BY_IDENTITY);
            }
        }

        // 2. Email-based match — requires verified email from the provider (S1).
        if ($email === '') {
            return $this->rejectionOrCreate($provider, null);
        }

        $emailVerified = $this->isEmailVerifiedByProvider($provider, $socialiteUser);
        $existingByEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existingByEmail !== null) {
            if (! $emailVerified) {
                // Found a local user by email, but the provider didn't vouch
                // for that email — do NOT auto-link.
                return new MatchResult(null, MatchResult::ACTION_REJECT_UNVERIFIED_EMAIL);
            }

            return new MatchResult($existingByEmail, MatchResult::ACTION_MATCH_BY_EMAIL);
        }

        // 3. No local user exists — depending on mode: create or reject.
        return $this->rejectionOrCreate($provider, $email);
    }

    /**
     * No local user by (provider, provider_user_id) AND none by email. The
     * caller must decide whether to create or reject based on Shop mode.
     */
    private function rejectionOrCreate(string $provider, ?string $email): MatchResult
    {
        // Shop mode: accounts come from the Shop only. A social provider
        // (google/discord/linkedin) is a sign-IN method, not a sign-UP channel.
        // The shop provider itself bypasses this rule — it IS the sign-up channel.
        if ($this->isShopMode() && $provider !== 'shop') {
            return new MatchResult(null, MatchResult::ACTION_REJECT_REGISTER_ON_SHOP_FIRST);
        }

        return new MatchResult(null, MatchResult::ACTION_CREATE);
    }

    private function isShopMode(): bool
    {
        return $this->settings->get('auth_shop_enabled', 'false') === 'true';
    }

    /**
     * Read the provider-specific verified-email flag from the raw user payload.
     * Google OIDC → email_verified. Discord → verified. LinkedIn OIDC →
     * email_verified. Unknown providers default to UNVERIFIED.
     *
     * Shop: the payload SHOULD carry `email_verified` — when present we honour
     * it (defence in depth — plan §S1). When the field is absent (older Shop
     * deployments that haven't added it yet) we trust the Shop as canonical
     * IdP. Once SaaSykit exposes the field this branch auto-enforces it.
     */
    private function isEmailVerifiedByProvider(string $provider, SocialiteUser $u): bool
    {
        $raw = is_array($u->user) ? $u->user : [];

        if ($provider === 'shop') {
            if (array_key_exists('email_verified', $raw)) {
                return (bool) $raw['email_verified'];
            }
            return true;
        }

        return match ($provider) {
            'google' => ($raw['email_verified'] ?? false) === true,
            'discord' => ($raw['verified'] ?? false) === true,
            'linkedin' => ($raw['email_verified'] ?? false) === true,
            default => false,
        };
    }
}
