<?php

namespace App\Events;

use App\Models\User;

class RecoveryCodesRegenerated
{
    public function __construct(
        public readonly User $user,
        public readonly ?string $ip,
        public readonly string $userAgent,
    ) {}
}
