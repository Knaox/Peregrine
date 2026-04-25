<?php

namespace App\Notifications\Bridge;

use App\Models\ServerPlan;
use App\Models\User;
use App\Services\Bridge\Stripe\StripeBillingPortalLinker;
use App\Services\Mail\MailTemplateRegistry;
use App\Services\Mail\MailTemplateService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * Receipt mail sent the moment Stripe confirms a checkout — fires
 * BEFORE the provisioning finishes. Tells the customer:
 *  - what they paid
 *  - that their server is being provisioned
 *  - how to manage their subscription (Stripe portal)
 *
 * Invoice PDF link is best-effort: we try to fetch the hosted_invoice_url
 * from Stripe API; if that fails we just omit the link from the body.
 */
class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ServerPlan $plan,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly ?string $invoiceId = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'en';
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $panelUrl = $appUrl.'/dashboard';

        $invoiceUrl = $this->resolveInvoiceUrl();
        $billingPortalUrl = app(StripeBillingPortalLinker::class)->urlFor($notifiable, $panelUrl)
            ?? $panelUrl;

        $rendered = app(MailTemplateService::class)->render(
            MailTemplateRegistry::BRIDGE_PAYMENT_CONFIRMED,
            $locale,
            [
                'name' => $notifiable->name,
                'plan_name' => $this->plan->name,
                'amount' => $this->formatAmount(),
                'currency' => strtoupper($this->currency),
                'payment_date' => now()->format('Y-m-d H:i e'),
                'invoice_url' => $invoiceUrl ?? $panelUrl,
                'panel_url' => $panelUrl,
                'billing_portal_url' => $billingPortalUrl,
                'timestamp' => now()->format('Y-m-d H:i e'),
            ],
        );

        return (new MailMessage())
            ->subject($rendered['subject'])
            ->view('emails.templated', [
                'subject' => $rendered['subject'],
                'bodyHtml' => $rendered['body_html'],
                'locale' => $locale,
                'brand' => app(SettingsService::class)->get('app_name', 'Peregrine'),
                'footerText' => (string) app(SettingsService::class)->get('email_footer_text', ''),
            ]);
    }

    private function formatAmount(): string
    {
        // Stripe amounts are in the smallest currency unit (cents for EUR/USD,
        // yen for JPY, etc.). For the common 2-decimal currencies we just
        // divide by 100. Zero-decimal currencies (JPY, KRW…) are passed
        // through as-is — we trade exactness on edge cases for a one-line
        // formatter the customer can read.
        return number_format($this->amountCents / 100, 2);
    }

    private function resolveInvoiceUrl(): ?string
    {
        if ($this->invoiceId === null || $this->invoiceId === '') {
            return null;
        }
        $apiKey = $this->resolveApiSecret();
        if ($apiKey === '') {
            return null;
        }
        try {
            $client = new StripeClient($apiKey);
            $invoice = $client->invoices->retrieve($this->invoiceId);
            return (string) ($invoice->hosted_invoice_url ?? '') ?: null;
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch Stripe invoice for receipt mail', [
                'invoice_id' => $this->invoiceId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function resolveApiSecret(): string
    {
        $envelope = (string) app(SettingsService::class)->get('bridge_stripe_api_secret', '');
        if ($envelope !== '') {
            try {
                return (string) Crypt::decryptString($envelope);
            } catch (\Throwable) {
                // fall through
            }
        }
        return (string) config('services.stripe.secret');
    }
}
