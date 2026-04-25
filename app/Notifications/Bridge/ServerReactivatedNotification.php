<?php

namespace App\Notifications\Bridge;

use App\Models\Server;
use App\Models\User;
use App\Services\Mail\MailTemplateRegistry;
use App\Services\Mail\MailTemplateService;
use App\Services\Pelican\PelicanNetworkService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a previously-suspended server has been brought back online
 * via a fresh Stripe checkout (resubscribe flow). The local row is the
 * same one, the Pelican server is the same — only the subscription is
 * new. Customer-facing message is celebratory: "your server is back".
 */
class ServerReactivatedNotification extends Notification implements ShouldQueue
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
            MailTemplateRegistry::BRIDGE_SERVER_REACTIVATED,
            $locale,
            [
                'name' => $notifiable->name,
                'plan_name' => $this->server->plan?->name ?? '—',
                'server_name' => $this->server->name,
                'ip_port' => $this->resolveAddress(),
                'panel_url' => $appUrl.'/servers/'.$this->server->id,
                'login_url' => $appUrl.'/login',
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

    private function resolveAddress(): string
    {
        if ($this->server->identifier === null) {
            return '—';
        }
        try {
            $allocations = app(PelicanNetworkService::class)
                ->listAllocations($this->server->identifier);

            foreach ($allocations as $alloc) {
                $attrs = $alloc['attributes'] ?? $alloc;
                if (($attrs['is_default'] ?? false) === true) {
                    $ip = $attrs['ip_alias'] ?? $attrs['ip'];
                    return "{$ip}:{$attrs['port']}";
                }
            }
            if (! empty($allocations)) {
                $attrs = $allocations[0]['attributes'] ?? $allocations[0];
                $ip = $attrs['ip_alias'] ?? $attrs['ip'];
                return "{$ip}:{$attrs['port']}";
            }
        } catch (\Throwable) {
            // graceful degradation
        }
        return '—';
    }
}
