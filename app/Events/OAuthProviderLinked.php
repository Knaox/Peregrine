<?php

namespace App\Events;

use App\Models\User;

class OAuthProviderLinked
{
    public function __construct(
        public readonly User $user,
        public readonly string $provider,
        public readonly ?string $ip,
        public readonly string $userAgent,
    ) {}
}
