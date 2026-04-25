<?php

namespace App\Notifications\Bridge;

use App\Models\Server;
use App\Models\User;
use App\Services\Bridge\Stripe\StripeBillingPortalLinker;
use App\Services\Mail\MailTemplateRegistry;
use App\Services\Mail\MailTemplateService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer when their Bridge-provisioned server is suspended
 * after a Stripe `customer.subscription.deleted` event. Includes the
 * scheduled hard-deletion date so the customer knows the recovery window.
 */
class ServerSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Server $server,
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
        $panelUrl = $appUrl.'/servers/'.$this->server->id;
        $linker = app(StripeBillingPortalLinker::class);

        // PRIMARY action = re-checkout on the shop, since Stripe forbids
        // re-activating a `canceled` subscription from the Customer Portal.
        // Falls back to the panel URL so the button is never broken.
        $resubscribeUrl = $linker->resubscribeUrlFor($this->server->plan) ?? $panelUrl;

        // SECONDARY action = Customer Portal for managing payment methods,
        // viewing invoice history, or cancelling other (still active) subs.
        // Same fallback chain as the ready-mail.
        $billingPortalUrl = $linker->urlFor($notifiable, $panelUrl) ?? $panelUrl;

        $rendered = app(MailTemplateService::class)->render(
            MailTemplateRegistry::BRIDGE_SERVER_SUSPENDED,
            $locale,
            [
                'name' => $notifiable->name,
                'plan_name' => $this->server->plan?->name ?? '—',
                'server_name' => $this->server->name,
                'scheduled_deletion_at' => $this->server->scheduled_deletion_at?->format('Y-m-d H:i e') ?? '—',
                'panel_url' => $panelUrl,
                'resubscribe_url' => $resubscribeUrl,
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
}
