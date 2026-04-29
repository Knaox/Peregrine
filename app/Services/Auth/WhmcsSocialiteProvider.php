<?php

namespace App\Services\Auth;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Custom Socialite driver for WHMCS — uses WHMCS's native OpenID Connect
 * identity provider (Configuration → System Settings → OpenID Connect).
 *
 * WHMCS exposes a fixed OIDC endpoint shape under <whmcs_base>/oauth/* :
 *   - /oauth/authorize.php  (authorization endpoint)
 *   - /oauth/token.php      (token endpoint)
 *   - /oauth/userinfo.php   (UserInfo endpoint, returns standard OIDC claims)
 * The admin only configures the WHMCS base URL ; the driver derives the
 * three endpoints from it. Standard OIDC scopes are requested
 * (`openid profile email`).
 *
 * The /oauth/userinfo.php payload follows the OIDC standard claim set
 * (`sub`, `email`, `email_verified`, `name`, `given_name`, `family_name`,
 * `preferred_username`). We honour `email_verified` as-is when it's a
 * boolean ; otherwise fall back to truthy detection on the optional
 * email_verified_at timestamp some forks expose.
 */
class WhmcsSocialiteProvider extends AbstractProvider
{
    protected $scopes = ['openid', 'profile', 'email'];

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

        // Normalise email_verified : OIDC says it should be boolean, but
        // be defensive against forks that send a timestamp instead.
        if (! array_key_exists('email_verified', $user)) {
            $user['email_verified'] = ! empty($user['email_verified_at']);
        } else {
            $user['email_verified'] = (bool) $user['email_verified'];
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): SocialiteUser
    {
        // Standard OIDC claims first, fall back to common WHMCS variants.
        $email = (string) ($user['email'] ?? '');
        $given = trim((string) ($user['given_name'] ?? $user['firstname'] ?? ''));
        $family = trim((string) ($user['family_name'] ?? $user['lastname'] ?? ''));
        $name = trim((string) ($user['name'] ?? trim($given.' '.$family)));
        $sub = (string) ($user['sub'] ?? $user['id'] ?? '');
        $nickname = (string) ($user['preferred_username'] ?? ($given !== '' ? $given : $email));

        return (new SocialiteUser())->setRaw($user)->map([
            'id' => $sub,
            'nickname' => $nickname !== '' ? $nickname : $email,
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            // WHMCS doesn't expose an avatar in userinfo ; fall back to
            // gravatar so the panel doesn't render a broken image.
            'avatar' => $email !== ''
                ? 'https://www.gravatar.com/avatar/'.md5(strtolower($email))
                : null,
        ]);
    }

    /**
     * Inject dynamic URLs derived from base_url at runtime, exactly as
     * ShopSocialiteProvider / PaymenterSocialiteProvider do.
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

        return config("services.whmcs.{$key}");
    }
}
