<?php

declare(strict_types=1);

namespace App\Events\Bridge;

use App\Models\ServerConfiguration;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by `StripeCheckoutHandler::handle()` right after a successful
 * checkout — independent of the provisioning chain. Listened by
 * `SendPaymentConfirmedNotification` (queued) which mails the customer a
 * payment receipt while the server is still being provisioned.
 *
 * Decoupled so analytics / Slack / plugin hooks can subscribe without
 * touching the webhook handler.
 */
class PaymentConfirmed
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly ServerConfiguration $configuration,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly ?string $invoiceId = null,
    ) {}
}
