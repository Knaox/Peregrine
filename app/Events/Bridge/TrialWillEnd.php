<?php

namespace App\Events\Bridge;

use App\Models\Server;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by StripeEventHandlers::handleTrialWillEnd() when Stripe sends the
 * J-3 reminder. Listened to by SendTrialWillEndNotification which emails
 * the customer with the upcoming charge date and a billing portal link to
 * update their card or cancel before the trial converts.
 */
class TrialWillEnd
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Server $server,
        public readonly CarbonInterface $trialEndsAt,
    ) {}
}
