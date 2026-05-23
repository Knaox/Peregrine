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

    /** @var array<int, string>|null Cached list of server names this invite covers. */
    private ?array $serverNamesCache = null;

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

        // Resolve the admin override (if any) and pick the default in PHP — never
        // pass the batch/single default through SettingsService::get(), which
        // caches the resolved value per key and would otherwise serve the first
        // caller's default (e.g. a single-server body) to a later batch email.
        $subjectKey = "email_tpl_invitation_subject_{$this->mailLocale}";
        $customSubject = $settings->get($subjectKey);
        $subjectTemplate = ($customSubject === null || $customSubject === '') ? $this->defaultSubject() : $customSubject;
        $subject = $this->replaceVars($subjectTemplate, $vars);

        return new Envelope(subject: $subject);
    }

    public function build(): self
    {
        $settings = app(SettingsService::class);
        $vars = $this->buildVariables();

        // Same caching caveat as the subject — resolve the default body in PHP.
        $bodyKey = "email_tpl_invitation_body_{$this->mailLocale}";
        $customBody = $settings->get($bodyKey);
        $bodyTemplate = ($customBody === null || $customBody === '') ? $this->defaultBody() : $customBody;
        $body = $this->replaceVars($bodyTemplate, $vars);

        // Le nom canonique du panel est stocké dans la DB (via la page
        // /admin/settings → SettingsService::set('app_name', …)). Pas
        // dans APP_NAME env, qui n'est souvent pas resynchronisé après
        // changement et reste à "Laravel" sur des installs Docker.
        $appName = $settings->get('app_name', config('app.name', 'Peregrine'));
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
        $settings = app(SettingsService::class);
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

        $permHtml = '<ul>'.implode('', array_map(fn (string $l) => "<li>{$l}</li>", $labels)).'</ul>';
        $appUrl = config('app.url', 'http://localhost');
        $appName = $settings->get('app_name', config('app.name', 'Peregrine'));

        $serverNames = $this->serverNames();
        $serversHtml = '<ul>'.implode('', array_map(fn (string $n) => "<li>{$n}</li>", $serverNames)).'</ul>';

        return [
            '{inviter_name}' => e($this->invitation->inviter?->name ?? 'Someone'),
            '{server_name}' => e($this->invitation->server?->name ?? 'a server'),
            '{servers_list}' => $serversHtml,
            '{server_count}' => (string) count($serverNames),
            '{permissions_list}' => $permHtml,
            '{accept_url}' => $appUrl.'/invite/'.$this->plainToken,
            '{expires_at}' => $this->invitation->expires_at?->format('M j, Y') ?? '',
            '{app_name}' => e($appName),
        ];
    }

    /**
     * The (HTML-escaped) names of every server this invitation covers — the
     * whole batch when batched, otherwise just the single server. Cached.
     *
     * @return array<int, string>
     */
    private function serverNames(): array
    {
        if ($this->serverNamesCache !== null) {
            return $this->serverNamesCache;
        }

        $fallback = [e($this->invitation->server?->name ?? 'a server')];

        if (! $this->invitation->batch_id) {
            return $this->serverNamesCache = $fallback;
        }

        $names = Invitation::where('batch_id', $this->invitation->batch_id)
            ->with('server:id,name')
            ->get()
            ->map(fn (Invitation $inv): string => e($inv->server?->name ?? 'a server'))
            ->values()
            ->all();

        return $this->serverNamesCache = $names ?: $fallback;
    }

    private function isBatch(): bool
    {
        return count($this->serverNames()) > 1;
    }

    private function defaultSubject(): string
    {
        if ($this->isBatch()) {
            return $this->mailLocale === 'fr'
                ? 'Vous avez été invité sur {server_count} serveurs'
                : "You've been invited to {server_count} servers";
        }

        return $this->mailLocale === 'fr'
            ? 'Vous avez été invité à rejoindre {server_name}'
            : "You've been invited to join {server_name}";
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function replaceVars(string $template, array $vars): string
    {
        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    private function defaultBody(): string
    {
        if ($this->isBatch()) {
            return $this->defaultBatchBody();
        }

        if ($this->mailLocale === 'fr') {
            return '<h1>Vous avez été invité sur un serveur</h1>'
                .'<p><strong>{inviter_name}</strong> vous a invité à rejoindre le serveur <strong>{server_name}</strong>.</p>'
                .'<p>Vous aurez les permissions suivantes :</p>{permissions_list}'
                .'<p style="text-align:center;margin:24px 0"><a href="{accept_url}" style="display:inline-block;padding:12px 28px;background:#e11d48;color:#fff;text-decoration:none;font-weight:600;border-radius:6px">Accepter l\'invitation</a></p>'
                .'<p style="color:#6b7280;font-size:13px">Cette invitation expire le {expires_at}.</p>'
                .'<p style="color:#6b7280;font-size:13px">Si vous n\'attendiez pas cette invitation, ignorez cet email.</p>';
        }

        return '<h1>You\'ve been invited to a server</h1>'
            .'<p><strong>{inviter_name}</strong> has invited you to join <strong>{server_name}</strong>.</p>'
            .'<p>You will have the following permissions:</p>{permissions_list}'
            .'<p style="text-align:center;margin:24px 0"><a href="{accept_url}" style="display:inline-block;padding:12px 28px;background:#e11d48;color:#fff;text-decoration:none;font-weight:600;border-radius:6px">Accept Invitation</a></p>'
            .'<p style="color:#6b7280;font-size:13px">This invitation expires on {expires_at}.</p>'
            .'<p style="color:#6b7280;font-size:13px">If you didn\'t expect this, you can safely ignore this email.</p>';
    }

    /**
     * Default body for a multi-server (batch) invitation: one accept link
     * ({accept_url}) authorizes every server in {servers_list} at once.
     */
    private function defaultBatchBody(): string
    {
        if ($this->mailLocale === 'fr') {
            return '<h1>Vous avez été invité sur plusieurs serveurs</h1>'
                .'<p><strong>{inviter_name}</strong> vous a invité à rejoindre <strong>{server_count}</strong> serveurs :</p>{servers_list}'
                .'<p>Sur chacun de ces serveurs, vous aurez les permissions suivantes :</p>{permissions_list}'
                .'<p style="text-align:center;margin:24px 0"><a href="{accept_url}" style="display:inline-block;padding:12px 28px;background:#e11d48;color:#fff;text-decoration:none;font-weight:600;border-radius:6px">Tout accepter</a></p>'
                .'<p style="color:#6b7280;font-size:13px">Un seul clic vous donne accès à tous ces serveurs. Cette invitation expire le {expires_at}.</p>'
                .'<p style="color:#6b7280;font-size:13px">Si vous n\'attendiez pas cette invitation, ignorez cet email.</p>';
        }

        return '<h1>You\'ve been invited to several servers</h1>'
            .'<p><strong>{inviter_name}</strong> has invited you to join <strong>{server_count}</strong> servers:</p>{servers_list}'
            .'<p>On each of these servers you will have the following permissions:</p>{permissions_list}'
            .'<p style="text-align:center;margin:24px 0"><a href="{accept_url}" style="display:inline-block;padding:12px 28px;background:#e11d48;color:#fff;text-decoration:none;font-weight:600;border-radius:6px">Accept all</a></p>'
            .'<p style="color:#6b7280;font-size:13px">A single click grants access to all of these servers. This invitation expires on {expires_at}.</p>'
            .'<p style="color:#6b7280;font-size:13px">If you didn\'t expect this, you can safely ignore this email.</p>';
    }
}
