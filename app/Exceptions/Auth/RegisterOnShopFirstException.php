<?php

namespace App\Exceptions\Auth;

/**
 * Thrown when a social login callback matches no local user and the panel
 * is in canonical mode (Shop or Paymenter). Users MUST register on the
 * canonical IdP first; social providers are alternative sign-in methods,
 * not sign-up channels. The frontend interpolates the canonical provider
 * name from /api/auth/providers.
 *
 * Kept name for API stability; class is generic across canonical IdPs.
 */
class RegisterOnShopFirstException extends SocialAuthException
{
    public function errorKey(): string
    {
        return 'auth.social.register_on_canonical_first';
    }

    public function statusCode(): int
    {
        return 403;
    }
}
