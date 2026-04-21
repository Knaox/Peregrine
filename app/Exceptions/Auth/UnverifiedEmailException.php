<?php

namespace App\Exceptions\Auth;

/**
 * Thrown when a social login callback returns an email the provider has NOT
 * marked as verified (plan §S1). Prevents account takeover via a provider
 * account created with someone else's email address.
 */
class UnverifiedEmailException extends SocialAuthException
{
    public function errorKey(): string
    {
        return 'auth.social.email_not_verified';
    }

    public function statusCode(): int
    {
        return 422;
    }
}
