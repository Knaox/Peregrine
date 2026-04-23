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
use Illuminate\Support\Facades\Password;

/**
 * Sent to a customer when their Bridge-provisioned server is ready to use.
 *
 * Auto-detects whether the user has a local password (template includes a
 * "set password" reset link) or signed in via OAuth (template just links
 * to the panel — no password setup needed).
 *
 * Resolves the server IP+port live from Pelican Client API at send time.
 * Cheap (one HTTP call) and avoids stale cache surprises in the customer's
 * inbox.
 */
class ServerReadyNotification extends Notification implements ShouldQueue
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
        $isOAuth = $notifiable->oauthIdentities()->exists();

        $variables = [
            'name' => $notifiable->name,
            'plan_name' => $this->server->plan?->name ?? '—',
            'server_name' => $this->server->name,
            'ip_port' => $this->resolveAddress(),
            'panel_url' => $appUrl.'/servers/'.$this->server->id,
            'timestamp' => now()->format('Y-m-d H:i e'),
        ];

        if (! $isOAuth) {
            $variables['reset_password_url'] = $this->buildPasswordResetUrl($notifiable);
        }

        $templateId = $isOAuth
            ? MailTemplateRegistry::BRIDGE_SERVER_READY_OAUTH
            : MailTemplateRegistry::BRIDGE_SERVER_READY_LOCAL;

        $rendered = app(MailTemplateService::class)->render($templateId, $locale, $variables);

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
            // Pelican unreachable — degrade gracefully, customer can still
            // log into the panel and find the address themselves.
        }
        return '—';
    }

    private function buildPasswordResetUrl(User $user): string
    {
        // Use Laravel's built-in password broker to generate a signed token
        // valid for the standard reset window (60 min by default — config
        // in `auth.passwords`). The route name `password.reset` is the
        // Laravel convention.
        $token = Password::broker()->createToken($user);

        return rtrim((string) config('app.url', ''), '/')
            .'/password/reset/'.$token.'?email='.urlencode($user->email);
    }
}
