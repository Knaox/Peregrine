<?php

namespace App\Services\Auth;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Custom Socialite driver for Paymenter (paymenter.org) — an open-source
 * billing platform built on Laravel Passport.
 *
 * Paymenter has a fixed Passport-based URL shape, so the admin only configures
 * a single base_url; the driver derives /oauth/authorize, /api/oauth/token,
 * /api/me from it. The matched scope is `profile` — the only scope Paymenter
 * exposes via its ScopeRegistry.
 *
 * The /api/me payload returns email_verified_at (ISO timestamp or null).
 * We normalise it to a synthetic boolean `email_verified` in the raw payload
 * so SocialUserMatcher can apply the same shape it uses for Google/Discord/
 * LinkedIn (and so the email-verified gate keeps working without driver-aware
 * logic outside this class).
 */
class PaymenterSocialiteProvider extends AbstractProvider
{
    protected $scopes = ['profile'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase((string) $this->getConfig('authorize_url'), $state);
    }

    protected function getTokenUrl(): string
    {
        return (string) $this->getConfig('token_url');
    }

    /**
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get((string) $this->getConfig('user_url'), [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ],
        ]);

        $decoded = json_decode((string) $response->getBody(), true);

        $user = is_array($decoded) ? $decoded : [];

        // Synthesize `email_verified` from `email_verified_at` so downstream
        // matcher logic stays uniform across providers.
        $user['email_verified'] = ! empty($user['email_verified_at']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): SocialiteUser
    {
        $first = trim((string) ($user['first_name'] ?? ''));
        $last = trim((string) ($user['last_name'] ?? ''));
        $name = trim($first.' '.$last);
        $email = (string) ($user['email'] ?? '');

        return (new SocialiteUser())->setRaw($user)->map([
            'id' => (string) ($user['id'] ?? ''),
            'nickname' => $first !== '' ? $first : $email,
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            // Paymenter doesn't surface an avatar in /me but generates a
            // gravatar server-side; replicate the URL locally so the panel
            // doesn't show a broken image.
            'avatar' => $email !== ''
                ? 'https://www.gravatar.com/avatar/'.md5(strtolower($email))
                : null,
        ]);
    }

    /**
     * Allow the service provider to inject the dynamic URLs derived from
     * base_url. Mirrors ShopSocialiteProvider::withExtraConfig.
     *
     * @param  array<string, mixed>  $extra
     */
    public function withExtraConfig(array $extra): self
    {
        $this->parameters = array_merge($this->parameters, $extra);

        return $this;
    }

    private function getConfig(string $key): mixed
    {
        if (array_key_exists($key, $this->parameters)) {
            return $this->parameters[$key];
        }

        return config("services.paymenter.{$key}");
    }
}
