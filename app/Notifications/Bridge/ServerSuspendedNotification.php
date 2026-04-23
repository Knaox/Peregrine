<?php

namespace App\Notifications\Bridge;

use App\Models\Server;
use App\Models\User;
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

        $rendered = app(MailTemplateService::class)->render(
            MailTemplateRegistry::BRIDGE_SERVER_SUSPENDED,
            $locale,
            [
                'name' => $notifiable->name,
                'plan_name' => $this->server->plan?->name ?? '—',
                'server_name' => $this->server->name,
                'scheduled_deletion_at' => $this->server->scheduled_deletion_at?->format('Y-m-d H:i e') ?? '—',
                'panel_url' => $appUrl.'/servers/'.$this->server->id,
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
