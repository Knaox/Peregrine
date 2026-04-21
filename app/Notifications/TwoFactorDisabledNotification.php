<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Mail\MailTemplateRegistry;
use App\Services\Mail\MailTemplateService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorDisabledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?string $ip,
        public readonly string $userAgent,
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
            MailTemplateRegistry::AUTH_2FA_DISABLED,
            $locale,
            [
                'name' => $notifiable->name,
                'timestamp' => now()->format('Y-m-d H:i:s e'),
                'ip' => $this->ip ?? 'unknown',
                'user_agent' => mb_substr($this->userAgent ?: 'unknown', 0, 160),
                'manage_url' => $appUrl.'/settings/security',
            ],
        );

        return (new MailMessage)
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
