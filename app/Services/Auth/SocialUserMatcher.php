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
     * Shop is treated as implicitly trusted (it IS our identity provider of
     * record). Google OIDC → email_verified. Discord → verified. LinkedIn
     * OIDC → email_verified. Unknown providers default to UNVERIFIED.
     */
    private function isEmailVerifiedByProvider(string $provider, SocialiteUser $u): bool
    {
        if ($provider === 'shop') {
            return true;
        }

        $raw = $u->user;
        if (! is_array($raw)) {
            return false;
        }

        return match ($provider) {
            'google' => ($raw['email_verified'] ?? false) === true,
            'discord' => ($raw['verified'] ?? false) === true,
            'linkedin' => ($raw['email_verified'] ?? false) === true,
            default => false,
        };
    }
}
