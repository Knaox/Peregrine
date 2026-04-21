<?php

namespace App\Services\Mail;

/**
 * Central registry for user-facing email templates whose subject/body are
 * editable from /admin/email-templates.
 *
 * Each entry declares:
 *   - id            stable identifier (used as the settings key suffix)
 *   - group         admin UI grouping (Auth, Invitations, …)
 *   - label         admin UI section title
 *   - variables     list of substitution tokens exposed to the admin
 *   - default_*     subject / body defaults per locale
 *
 * The MailTemplateService resolves any user-facing email through this list:
 * admin overrides in the settings table take precedence; otherwise the
 * defaults below are used.
 */
final class MailTemplateRegistry
{
    public const AUTH_2FA_ENABLED = 'auth_2fa_enabled';

    public const AUTH_2FA_DISABLED = 'auth_2fa_disabled';

    public const AUTH_2FA_RECOVERY_REGENERATED = 'auth_2fa_recovery_regenerated';

    public const AUTH_SOCIAL_LINKED = 'auth_social_linked';

    public const AUTH_SOCIAL_UNLINKED = 'auth_social_unlinked';

    /**
     * @return array<int, array{id: string, group: string, label: string, description: string, variables: array<int, string>, default_subject_en: string, default_subject_fr: string, default_body_en: string, default_body_fr: string}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => self::AUTH_2FA_ENABLED,
                'group' => 'Auth',
                'label' => '2FA enabled',
                'description' => 'Sent when a user activates two-factor authentication on their account.',
                'variables' => ['name', 'server_name', 'timestamp', 'ip', 'user_agent', 'manage_url'],
                'default_subject_en' => '2FA enabled on your {server_name} account',
                'default_subject_fr' => '2FA activée sur votre compte {server_name}',
                'default_body_en' => self::body2faEnabledEn(),
                'default_body_fr' => self::body2faEnabledFr(),
            ],
            [
                'id' => self::AUTH_2FA_DISABLED,
                'group' => 'Auth',
                'label' => '2FA disabled',
                'description' => 'Sent when a user turns off two-factor authentication.',
                'variables' => ['name', 'server_name', 'timestamp', 'ip', 'user_agent', 'manage_url'],
                'default_subject_en' => '2FA disabled on your {server_name} account',
                'default_subject_fr' => '2FA désactivée sur votre compte {server_name}',
                'default_body_en' => self::body2faDisabledEn(),
                'default_body_fr' => self::body2faDisabledFr(),
            ],
            [
                'id' => self::AUTH_2FA_RECOVERY_REGENERATED,
                'group' => 'Auth',
                'label' => 'Recovery codes regenerated',
                'description' => 'Sent when a user regenerates their 2FA recovery codes.',
                'variables' => ['name', 'server_name', 'timestamp', 'ip', 'user_agent', 'manage_url'],
                'default_subject_en' => 'Your {server_name} recovery codes were regenerated',
                'default_subject_fr' => 'Vos codes de récupération {server_name} ont été régénérés',
                'default_body_en' => self::bodyRecoveryEn(),
                'default_body_fr' => self::bodyRecoveryFr(),
            ],
            [
                'id' => self::AUTH_SOCIAL_LINKED,
                'group' => 'Auth',
                'label' => 'OAuth provider linked',
                'description' => 'Sent when a social provider (Google, Discord, LinkedIn, Shop) is linked to a user account.',
                'variables' => ['name', 'provider', 'server_name', 'timestamp', 'ip', 'user_agent', 'manage_url'],
                'default_subject_en' => '{provider} linked to your {server_name} account',
                'default_subject_fr' => '{provider} lié à votre compte {server_name}',
                'default_body_en' => self::bodyLinkedEn(),
                'default_body_fr' => self::bodyLinkedFr(),
            ],
            [
                'id' => self::AUTH_SOCIAL_UNLINKED,
                'group' => 'Auth',
                'label' => 'OAuth provider unlinked',
                'description' => 'Sent when a social provider is removed from a user account.',
                'variables' => ['name', 'provider', 'server_name', 'timestamp', 'ip', 'user_agent', 'manage_url'],
                'default_subject_en' => '{provider} unlinked from your {server_name} account',
                'default_subject_fr' => '{provider} délié de votre compte {server_name}',
                'default_body_en' => self::bodyUnlinkedEn(),
                'default_body_fr' => self::bodyUnlinkedFr(),
            ],
        ];
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $tpl) {
            if ($tpl['id'] === $id) {
                return $tpl;
            }
        }

        return null;
    }

    private static function bodyMeta(string $bodyLead): string
    {
        return $bodyLead."\n\n"
            .'<hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;">'
            .'<p style="font-size:13px;color:#6b7280;margin:4px 0;">'
            .'<strong>Time:</strong> {timestamp}<br>'
            .'<strong>IP:</strong> {ip}<br>'
            .'<strong>User agent:</strong> {user_agent}'
            .'</p>'
            .'<p style="text-align:center;margin:24px 0;">'
            .'<a href="{manage_url}" style="display:inline-block;padding:10px 22px;background:#e11d48;color:#fff;text-decoration:none;font-weight:600;border-radius:6px;">Review security settings</a>'
            .'</p>'
            .'<p style="font-size:12px;color:#9ca3af;">If this wasn\'t you, contact your administrator immediately.</p>';
    }

    private static function body2faEnabledEn(): string
    {
        return self::bodyMeta(
            '<h1>2FA activated</h1>'
            .'<p>Hi {name}, two-factor authentication was just turned on for your {server_name} account.</p>'
        );
    }

    private static function body2faEnabledFr(): string
    {
        return self::bodyMeta(
            '<h1>2FA activée</h1>'
            .'<p>Bonjour {name}, la double authentification vient d\'être activée sur votre compte {server_name}.</p>'
        );
    }

    private static function body2faDisabledEn(): string
    {
        return self::bodyMeta(
            '<h1>2FA turned off</h1>'
            .'<p>Hi {name}, two-factor authentication was just turned off for your {server_name} account.</p>'
        );
    }

    private static function body2faDisabledFr(): string
    {
        return self::bodyMeta(
            '<h1>2FA désactivée</h1>'
            .'<p>Bonjour {name}, la double authentification vient d\'être désactivée sur votre compte {server_name}.</p>'
        );
    }

    private static function bodyRecoveryEn(): string
    {
        return self::bodyMeta(
            '<h1>New recovery codes generated</h1>'
            .'<p>Hi {name}, a new set of 2FA recovery codes was just generated for your {server_name} account. '
            .'Previous codes no longer work.</p>'
        );
    }

    private static function bodyRecoveryFr(): string
    {
        return self::bodyMeta(
            '<h1>Nouveaux codes de récupération</h1>'
            .'<p>Bonjour {name}, un nouvel ensemble de codes de récupération 2FA a été généré pour votre compte {server_name}. '
            .'Les anciens codes ne fonctionnent plus.</p>'
        );
    }

    private static function bodyLinkedEn(): string
    {
        return self::bodyMeta(
            '<h1>{provider} linked</h1>'
            .'<p>Hi {name}, <strong>{provider}</strong> was just linked as a sign-in method on your {server_name} account.</p>'
        );
    }

    private static function bodyLinkedFr(): string
    {
        return self::bodyMeta(
            '<h1>{provider} lié</h1>'
            .'<p>Bonjour {name}, <strong>{provider}</strong> vient d\'être lié comme méthode de connexion sur votre compte {server_name}.</p>'
        );
    }

    private static function bodyUnlinkedEn(): string
    {
        return self::bodyMeta(
            '<h1>{provider} unlinked</h1>'
            .'<p>Hi {name}, <strong>{provider}</strong> was just removed as a sign-in method on your {server_name} account.</p>'
        );
    }

    private static function bodyUnlinkedFr(): string
    {
        return self::bodyMeta(
            '<h1>{provider} délié</h1>'
            .'<p>Bonjour {name}, <strong>{provider}</strong> vient d\'être retiré comme méthode de connexion de votre compte {server_name}.</p>'
        );
    }
}
