<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecoveryCodesRegeneratedNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject(__('auth.2fa.mail.recovery_regenerated.subject', locale: $locale))
            ->greeting(__('auth.2fa.mail.greeting', ['name' => $notifiable->name], $locale))
            ->line(__('auth.2fa.mail.recovery_regenerated.body', locale: $locale))
            ->line(__('auth.2fa.mail.meta.timestamp', ['time' => now()->format('Y-m-d H:i:s e')], $locale))
            ->line(__('auth.2fa.mail.meta.ip', ['ip' => $this->ip ?? 'unknown'], $locale))
            ->line(__('auth.2fa.mail.meta.user_agent', ['ua' => mb_substr($this->userAgent ?: 'unknown', 0, 160)], $locale))
            ->action(__('auth.2fa.mail.cta.review_security', locale: $locale), $appUrl.'/settings/security')
            ->line(__('auth.2fa.mail.footer.not_me', locale: $locale));
    }
}
