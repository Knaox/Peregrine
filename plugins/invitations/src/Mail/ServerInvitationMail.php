<?php

namespace Plugins\Invitations\Mail;

use App\Services\SettingsService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Plugins\Invitations\Models\Invitation;
use Plugins\Invitations\Services\PermissionRegistry;

/**
 * @internal Do NOT queue this Mailable directly — never add ShouldQueue.
 * Plugin classes must not be serialized into the queue. Dispatch via App\Jobs\SendPluginMail.
 */
final class ServerInvitationMail extends Mailable
{

    private string $mailLocale = 'en';

    private Invitation $invitation;

    public function __construct(
        int $invitationId,
        public readonly string $plainToken,
        string $locale = 'en',
    ) {
        $this->mailLocale = in_array($locale, ['en', 'fr'], true) ? $locale : 'en';
        $this->invitation = Invitation::with(['server', 'inviter'])->findOrFail($invitationId);
    }

    public function envelope(): Envelope
    {
        $settings = app(SettingsService::class);
        $vars = $this->buildVariables();

        $subjectKey = "email_tpl_invitation_subject_{$this->mailLocale}";
        $defaultSubject = $this->mailLocale === 'fr'
            ? 'Vous avez été invité à rejoindre {server_name}'
            : "You've been invited to join {server_name}";

        $subject = $this->replaceVars($settings->get($subjectKey, $defaultSubject), $vars);

        return new Envelope(subject: $subject);
    }

    public function build(): self
    {
        $settings = app(SettingsService::class);
        $vars = $this->buildVariables();

        $bodyKey = "email_tpl_invitation_body_{$this->mailLocale}";
        $bodyTemplate = $settings->get($bodyKey, $this->defaultBody());
        $body = $this->replaceVars($bodyTemplate, $vars);

        $appName = config('app.name', 'Peregrine');
        $appUrl = config('app.url', 'http://localhost');

        return $this->view('mail.layouts.base', [
            'slot' => $body,
            'appName' => $appName,
            'appUrl' => $appUrl,
            'logoUrl' => $settings->getEmailLogoUrl(),
            'footerText' => $settings->get('email_footer_text', ''),
            'locale' => $this->mailLocale,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function buildVariables(): array
    {
        $registry = app(PermissionRegistry::class);
        $allGroups = $registry->getGroups();

        $labels = [];
        foreach ($this->invitation->permissions ?? [] as $permKey) {
            foreach ($allGroups as $group) {
                if (isset($group['permissions'][$permKey])) {
                    $labels[] = $group['permissions'][$permKey][$this->mailLocale]
                        ?? $group['permissions'][$permKey]['en']
                        ?? $permKey;
                    break;
                }
            }
        }

        $permHtml = '<ul>' . implode('', array_map(fn (string $l) => "<li>{$l}</li>", $labels)) . '</ul>';
        $appUrl = config('app.url', 'http://localhost');

        return [
            '{inviter_name}' => e($this->invitation->inviter?->name ?? 'Someone'),
            '{server_name}' => e($this->invitation->server?->name ?? 'a server'),
            '{permissions_list}' => $permHtml,
            '{accept_url}' => $appUrl . '/invite/' . $this->plainToken,
            '{expires_at}' => $this->invitation->expires_at?->format('M j, Y') ?? '',
            '{app_name}' => e(config('app.name', 'Peregrine')),
        ];
    }

    /**
     * @param array<string, string> $vars
     */
    private function replaceVars(string $template, array $vars): string
    {
        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    private function defaultBody(): string
    {
        if ($this->mailLocale === 'fr') {
            return '<h1>Vous avez été invité sur un serveur</h1>'
                . '<p><strong>{inviter_name}</strong> vous a invité à rejoindre le serveur <strong>{server_name}</strong>.</p>'
                . '<p>Vous aurez les permissions suivantes :</p>{permissions_list}'
                . '<p style="text-align:center;margin:24px 0"><a href="{accept_url}" style="display:inline-block;padding:12px 28px;background:#e11d48;color:#fff;text-decoration:none;font-weight:600;border-radius:6px">Accepter l\'invitation</a></p>'
                . '<p style="color:#6b7280;font-size:13px">Cette invitation expire le {expires_at}.</p>'
                . '<p style="color:#6b7280;font-size:13px">Si vous n\'attendiez pas cette invitation, ignorez cet email.</p>';
        }

        return '<h1>You\'ve been invited to a server</h1>'
            . '<p><strong>{inviter_name}</strong> has invited you to join <strong>{server_name}</strong>.</p>'
            . '<p>You will have the following permissions:</p>{permissions_list}'
            . '<p style="text-align:center;margin:24px 0"><a href="{accept_url}" style="display:inline-block;padding:12px 28px;background:#e11d48;color:#fff;text-decoration:none;font-weight:600;border-radius:6px">Accept Invitation</a></p>'
            . '<p style="color:#6b7280;font-size:13px">This invitation expires on {expires_at}.</p>'
            . '<p style="color:#6b7280;font-size:13px">If you didn\'t expect this, you can safely ignore this email.</p>';
    }
}
