<?php

namespace App\Exceptions\Auth;

/**
 * Thrown when a user tries to unlink their ONLY remaining login method
 * (plan §S7). Prevents self-lockout: must have either a password set OR
 * at least one other linked OAuth identity.
 */
class LastLoginMethodException extends SocialAuthException
{
    public function errorKey(): string
    {
        return 'auth.social.cannot_unlink_last_method';
    }

    public function statusCode(): int
    {
        return 422;
    }
}
