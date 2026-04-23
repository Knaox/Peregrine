<?php

namespace App\Notifications\Bridge;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to admins when Stripe reports `invoice.payment_failed`. Information-
 * only — Stripe handles the dunning automatically (will retry the charge
 * per the Smart Retries config in the Stripe Dashboard) and emits
 * `subscription.updated → past_due` after each failed retry, then
 * eventually `subscription.deleted` if all attempts fail. Peregrine reacts
 * to those status events; this notification just keeps admins in the loop
 * so they can reach out proactively to high-value customers.
 */
class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $stripeInvoiceId,
        public readonly ?string $stripeSubscriptionId,
        public readonly ?string $customerEmail,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly ?string $nextAttemptAt,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->amountCents / 100, 2);
        $msg = (new MailMessage())
            ->subject("Stripe payment failed — {$amount} {$this->currency}")
            ->line("A Stripe invoice payment just failed.")
            ->line("**Invoice**: {$this->stripeInvoiceId}")
            ->line("**Amount**: {$amount} {$this->currency}");

        if ($this->customerEmail !== null) {
            $msg->line("**Customer email**: {$this->customerEmail}");
        }
        if ($this->stripeSubscriptionId !== null) {
            $msg->line("**Subscription**: {$this->stripeSubscriptionId}");
        }
        if ($this->nextAttemptAt !== null) {
            $msg->line("Stripe will retry the charge automatically. Next attempt: {$this->nextAttemptAt}.");
        } else {
            $msg->line("Stripe has stopped retrying. The subscription will move to past_due/cancelled depending on dunning rules.");
        }

        return $msg
            ->line("This is informational — Peregrine reacts automatically when the subscription status changes.");
    }
}
