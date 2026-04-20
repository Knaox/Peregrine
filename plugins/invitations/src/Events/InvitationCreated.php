<?php

namespace Plugins\Invitations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Plugins\Invitations\Models\Invitation;

class InvitationCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Invitation $invitation,
    ) {}
}
