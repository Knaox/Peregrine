<?php

namespace App\Exceptions\Auth;

/**
 * Thrown when a social login callback returns an email the provider has NOT
 * marked as verified (plan §S1). Prevents account takeover via a provider
 * account created with someone else's email address.
 */
class UnverifiedEmailException extends SocialAuthException
{
    public function __construct(private readonly ?string $provider = null)
    {
        parent::__construct();
    }

    public function errorKey(): string
    {
        return $this->provider === 'shop'
            ? 'auth.social.email_not_verified_shop'
            : 'auth.social.email_not_verified';
    }

    public function statusCode(): int
    {
        return 422;
    }
}
