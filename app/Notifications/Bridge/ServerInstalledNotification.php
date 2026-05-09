<?php

namespace App\Notifications\Bridge;

use App\Models\Server;
use App\Models\User;
use App\Services\Mail\MailTemplateRegistry;
use App\Services\Mail\MailTemplateRegistry\BridgeMailBodies;
use App\Services\Mail\MailTemplateService;
use App\Services\Pelican\PelicanNetworkService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Sent when Pelican finishes installing the server (status flipped out of
 * `installing`). This is the email customers actually wait for : "your
 * server is now playable, here is the address".
 *
 * Conditional password block : when the user has no local password set
 * AND no OAuth provider linked (= account was created during the
 * checkout flow but they've never signed in yet), the email injects a
 * "Set your password" CTA pointing at a fresh `Password::createToken`
 * reset link valid for 7 days. If either condition is missing — they
 * already logged in once, or they have a Google / Discord / Shop OAuth
 * link — the block is omitted (we don't want to confuse a user who
 * already has a working sign-in path).
 */
class ServerInstalledNotification extends Notification implements ShouldQueue
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
            MailTemplateRegistry::BRIDGE_SERVER_INSTALLED,
            $locale,
            [
                'name' => $notifiable->name,
                'plan_name' => $this->server->serverConfiguration?->internal_name ?? '—',
                'server_name' => $this->server->name,
                'ip_port' => $this->resolveAddress(),
                'panel_url' => $appUrl.'/servers/'.$this->server->id,
                'login_url' => $appUrl.'/login',
                'timestamp' => now()->format('Y-m-d H:i e'),
                'password_block' => $this->buildPasswordBlock($notifiable, $locale),
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

    /**
     * Render the inline "Set your password" CTA, or empty string when
     * the user already has a working sign-in path. The two skip
     * conditions are intentional :
     *   1. Has a local password (`$user->password` not empty) → they
     *      already signed in at least once or completed a previous
     *      reset flow. Telling them to "create a password" would be
     *      confusing.
     *   2. Has at least one OAuth identity → they sign in via Shop /
     *      Google / Discord / etc. Asking them to set a local password
     *      would push a sign-in path they don't need.
     */
    private function buildPasswordBlock(User $user, string $locale): string
    {
        $isOAuth = $user->oauthIdentities()->exists();
        $hasLocalPassword = ! empty($user->password);
        if ($isOAuth || $hasLocalPassword) {
            return '';
        }

        $token = Password::broker()->createToken($user);
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        return $locale === 'fr'
            ? BridgeMailBodies::passwordBlockFr($resetUrl)
            : BridgeMailBodies::passwordBlockEn($resetUrl);
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
