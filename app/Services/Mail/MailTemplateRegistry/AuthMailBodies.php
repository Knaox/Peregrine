<?php

namespace App\Services\Mail\MailTemplateRegistry;

/**
 * HTML body factories for the "Auth" group of email templates
 * (2FA enabled / disabled / recovery regenerated, OAuth linked / unlinked).
 *
 * Each public static method returns the default HTML body for one template
 * in one locale. They share the `bodyMeta()` wrapper which appends the
 * forensic block (timestamp / IP / user agent) + the "Review security
 * settings" CTA — these are security alert mails.
 *
 * Extracted from `MailTemplateRegistry` to honour the 300-line file rule
 * and keep the registry focused on the schema (constants + groups + map).
 */
final class AuthMailBodies
{
    public static function twoFactorEnabledEn(): string
    {
        return self::wrap(
            '<h1>2FA activated</h1>'
            .'<p>Hi {name}, two-factor authentication was just turned on for your {server_name} account.</p>'
        );
    }

    public static function twoFactorEnabledFr(): string
    {
        return self::wrap(
            '<h1>2FA activée</h1>'
            .'<p>Bonjour {name}, la double authentification vient d\'être activée sur votre compte {server_name}.</p>'
        );
    }

    public static function twoFactorDisabledEn(): string
    {
        return self::wrap(
            '<h1>2FA turned off</h1>'
            .'<p>Hi {name}, two-factor authentication was just turned off for your {server_name} account.</p>'
        );
    }

    public static function twoFactorDisabledFr(): string
    {
        return self::wrap(
            '<h1>2FA désactivée</h1>'
            .'<p>Bonjour {name}, la double authentification vient d\'être désactivée sur votre compte {server_name}.</p>'
        );
    }

    public static function recoveryRegeneratedEn(): string
    {
        return self::wrap(
            '<h1>New recovery codes generated</h1>'
            .'<p>Hi {name}, a new set of 2FA recovery codes was just generated for your {server_name} account. '
            .'Previous codes no longer work.</p>'
        );
    }

    public static function recoveryRegeneratedFr(): string
    {
        return self::wrap(
            '<h1>Nouveaux codes de récupération</h1>'
            .'<p>Bonjour {name}, un nouvel ensemble de codes de récupération 2FA a été généré pour votre compte {server_name}. '
            .'Les anciens codes ne fonctionnent plus.</p>'
        );
    }

    public static function socialLinkedEn(): string
    {
        return self::wrap(
            '<h1>{provider} linked</h1>'
            .'<p>Hi {name}, <strong>{provider}</strong> was just linked as a sign-in method on your {server_name} account.</p>'
        );
    }

    public static function socialLinkedFr(): string
    {
        return self::wrap(
            '<h1>{provider} lié</h1>'
            .'<p>Bonjour {name}, <strong>{provider}</strong> vient d\'être lié comme méthode de connexion sur votre compte {server_name}.</p>'
        );
    }

    public static function socialUnlinkedEn(): string
    {
        return self::wrap(
            '<h1>{provider} unlinked</h1>'
            .'<p>Hi {name}, <strong>{provider}</strong> was just removed as a sign-in method on your {server_name} account.</p>'
        );
    }

    public static function socialUnlinkedFr(): string
    {
        return self::wrap(
            '<h1>{provider} délié</h1>'
            .'<p>Bonjour {name}, <strong>{provider}</strong> vient d\'être retiré comme méthode de connexion de votre compte {server_name}.</p>'
        );
    }

    /**
     * Forensic footer + "review security settings" CTA shared by every Auth
     * template. Variables `{timestamp}`, `{ip}`, `{user_agent}`, `{manage_url}`
     * are interpolated by `MailTemplateService::render()`.
     */
    private static function wrap(string $bodyLead): string
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
}
