<?php

namespace App\Listeners\Bridge;

use App\Events\Bridge\TrialWillEnd;
use App\Notifications\Bridge\TrialWillEndNotification;
use App\Services\Integrations\IntegrationStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTrialWillEndNotification implements ShouldQueue
{
    public function handle(TrialWillEnd $event): void
    {
        // Same Shop+Stripe-only gate as the rest of the Bridge listeners —
        // Paymenter handles its own customer communications.
        if (! app(IntegrationStatusService::class)->hasStripeConfigured()) {
            return;
        }
        $event->user->notify(new TrialWillEndNotification(
            server: $event->server,
            trialEndsAt: $event->trialEndsAt,
        ));
    }
}
