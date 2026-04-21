<?php

namespace App\Exceptions\Auth;

/**
 * Thrown when a social login callback matches no local user and the panel
 * is in Shop mode. Users MUST register on the Shop first; social providers
 * are alternative sign-in methods, not sign-up channels.
 */
class RegisterOnShopFirstException extends SocialAuthException
{
    public function errorKey(): string
    {
        return 'auth.social.register_on_shop_first';
    }

    public function statusCode(): int
    {
        return 403;
    }
}
