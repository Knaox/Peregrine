<?php

namespace App\Notifications\Bridge;

use App\Models\Server;
use App\Models\User;
use App\Services\Bridge\Stripe\StripeBillingPortalLinker;
use App\Services\Mail\MailTemplateRegistry;
use App\Services\Mail\MailTemplateService;
use App\Services\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer 3 days before a free trial converts to a paid
 * charge (Stripe customer.subscription.trial_will_end). Tells them when
 * the card will be charged and links to the Customer Portal so they can
 * update their card, change plan, or cancel before the conversion.
 */
class TrialWillEndNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Server $server,
        public readonly CarbonInterface $trialEndsAt,
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
        $billingPortalUrl = $linker->urlFor($notifiable, $panelUrl) ?? $panelUrl;

        $rendered = app(MailTemplateService::class)->render(
            MailTemplateRegistry::BRIDGE_TRIAL_WILL_END,
            $locale,
            [
                'name' => $notifiable->name,
                'plan_name' => $this->server->plan?->name ?? '—',
                'server_name' => $this->server->name,
                'trial_ends_at' => $this->trialEndsAt->format('Y-m-d H:i e'),
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
}
