<?php

namespace Plugins\Invitations\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Plugins\Invitations\Models\Invitation;

class InvitationAccepted
{
    use Dispatchable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly User $user,
    ) {}
}
