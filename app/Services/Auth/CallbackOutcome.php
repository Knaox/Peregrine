<?php

namespace App\Services\Auth;

use App\Models\User;

/**
 * Result of SocialAuthService::handleCallback — a fully-resolved user +
 * whether the flow should deflect into the 2FA challenge or log in straight
 * away.
 */
final readonly class CallbackOutcome
{
    public function __construct(
        public User $user,
        public bool $requires2fa,
        public bool $providerWasJustLinked,
    ) {}
}
