<?php

namespace Plugins\Invitations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Plugins\Invitations\Models\Invitation;

class InvitationRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly Invitation $invitation,
    ) {}
}
