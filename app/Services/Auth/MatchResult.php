<?php

namespace App\Services\Auth;

use App\Models\User;

/**
 * DTO returned by SocialUserMatcher::match(). Lets SocialAuthService take
 * different branches (link existing, create new, reject) without the matcher
 * knowing about events or persistence.
 */
final readonly class MatchResult
{
    public const ACTION_MATCH_BY_IDENTITY = 'match_by_identity';

    public const ACTION_MATCH_BY_EMAIL = 'match_by_email';

    public const ACTION_CREATE = 'create';

    public const ACTION_REJECT_UNVERIFIED_EMAIL = 'reject_unverified_email';

    public const ACTION_REJECT_REGISTER_ON_SHOP_FIRST = 'reject_register_on_shop_first';

    public function __construct(
        public ?User $user,
        public string $action,
    ) {}
}
