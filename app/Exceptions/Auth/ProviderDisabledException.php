<?php

namespace App\Exceptions\Auth;

/**
 * Thrown when an inbound social auth request names a provider that is not
 * enabled in the current settings. Renders as a clean 404 so the frontend
 * can surface a localized "this provider is not available" error.
 */
class ProviderDisabledException extends SocialAuthException
{
    public function errorKey(): string
    {
        return 'auth.social.provider_disabled';
    }

    public function statusCode(): int
    {
        return 404;
    }
}
