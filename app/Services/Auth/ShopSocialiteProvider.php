<?php

namespace App\Services\Auth;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Custom Socialite driver for the BiomeBounty Shop — a generic OAuth2 code
 * flow where the endpoint URLs are stored per-instance in the settings table
 * and injected at runtime by AuthProviderRegistry::configureSocialite('shop').
 *
 * Emits a vanilla Socialite\Two\User so the rest of the pipeline (matcher,
 * service, controller) treats Shop identically to the built-in drivers.
 */
class ShopSocialiteProvider extends AbstractProvider
{
    /** Shop issues profile data under the top-level `email` / `id` / `name` keys. */
    protected $scopeSeparator = ' ';

    /**
     * The Shop's OAuth2 authorization endpoint — read dynamically from the
     * injected config so admins can point this at any OAuth2-compliant server.
     */
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

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): SocialiteUser
    {
        return (new SocialiteUser())->setRaw($user)->map([
            'id' => (string) ($user['id'] ?? ''),
            'nickname' => $user['name'] ?? ($user['email'] ?? ''),
            'name' => (string) ($user['name'] ?? $user['email'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'avatar' => $user['avatar'] ?? null,
        ]);
    }

    /**
     * Allow the service provider to pass through the dynamic URL config.
     * Socialite core only recognises client_id, client_secret and redirect
     * out of the box — the authorize/token/user URLs live in an extra array
     * keyed by the driver name, merged here at construction time.
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

        return config("services.shop.{$key}");
    }
}
