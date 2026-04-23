<?php

namespace App\Services\Mail\MailTemplateRegistry;

/**
 * HTML body factories for the "Bridge" group of email templates
 * (server ready local / OAuth, server suspended).
 *
 * Distinct from `AuthMailBodies` because these are commercial mails — no
 * IP/UA forensic block, no "review security" CTA. The `wrap()` helper here
 * adds a green primary-action button + a discreet timestamp footer.
 *
 * Extracted from `MailTemplateRegistry` to honour the 300-line file rule.
 */
final class BridgeMailBodies
{
    public static function serverReadyLocalEn(): string
    {
        return self::wrap(
            '<h1>Your {plan_name} server is ready 🎮</h1>'
            .'<p>Hi {name}, your <strong>{server_name}</strong> server has been provisioned and is ready to use.</p>'
            .'<p style="background:#f3f4f6;padding:14px 18px;border-radius:8px;font-family:ui-monospace,monospace;">'
            .'<strong>Server address:</strong> {ip_port}'
            .'</p>'
            .'<p>Since you don\'t have a password yet, set one to access the panel:</p>'
            .'<p style="text-align:center;margin:20px 0;">'
            .'<a href="{reset_password_url}" style="display:inline-block;padding:10px 22px;background:#3b82f6;color:#fff;text-decoration:none;font-weight:600;border-radius:6px;">Set my password</a>'
            .'</p>'
            .'<p style="font-size:12px;color:#6b7280;">This link is valid for 7 days.</p>',
            'Open the panel',
            '{panel_url}'
        );
    }

    public static function serverReadyLocalFr(): string
    {
        return self::wrap(
            '<h1>Votre serveur {plan_name} est prêt 🎮</h1>'
            .'<p>Bonjour {name}, votre serveur <strong>{server_name}</strong> a été provisionné et est prêt à l\'emploi.</p>'
            .'<p style="background:#f3f4f6;padding:14px 18px;border-radius:8px;font-family:ui-monospace,monospace;">'
            .'<strong>Adresse du serveur :</strong> {ip_port}'
            .'</p>'
            .'<p>Vous n\'avez pas encore de mot de passe — définissez-en un pour accéder au panel :</p>'
            .'<p style="text-align:center;margin:20px 0;">'
            .'<a href="{reset_password_url}" style="display:inline-block;padding:10px 22px;background:#3b82f6;color:#fff;text-decoration:none;font-weight:600;border-radius:6px;">Définir mon mot de passe</a>'
            .'</p>'
            .'<p style="font-size:12px;color:#6b7280;">Ce lien est valide pendant 7 jours.</p>',
            'Ouvrir le panel',
            '{panel_url}'
        );
    }

    public static function serverReadyOAuthEn(): string
    {
        return self::wrap(
            '<h1>Your {plan_name} server is ready 🎮</h1>'
            .'<p>Hi {name}, your <strong>{server_name}</strong> server has been provisioned and is ready to use.</p>'
            .'<p style="background:#f3f4f6;padding:14px 18px;border-radius:8px;font-family:ui-monospace,monospace;">'
            .'<strong>Server address:</strong> {ip_port}'
            .'</p>'
            .'<p>Sign in with your usual provider — no password needed.</p>',
            'Open the panel',
            '{panel_url}'
        );
    }

    public static function serverReadyOAuthFr(): string
    {
        return self::wrap(
            '<h1>Votre serveur {plan_name} est prêt 🎮</h1>'
            .'<p>Bonjour {name}, votre serveur <strong>{server_name}</strong> a été provisionné et est prêt à l\'emploi.</p>'
            .'<p style="background:#f3f4f6;padding:14px 18px;border-radius:8px;font-family:ui-monospace,monospace;">'
            .'<strong>Adresse du serveur :</strong> {ip_port}'
            .'</p>'
            .'<p>Connectez-vous avec votre fournisseur habituel — aucun mot de passe nécessaire.</p>',
            'Ouvrir le panel',
            '{panel_url}'
        );
    }

    public static function serverSuspendedEn(): string
    {
        return self::wrap(
            '<h1>Your {plan_name} server has been suspended</h1>'
            .'<p>Hi {name}, the subscription for your <strong>{server_name}</strong> server was cancelled and the server has been suspended.</p>'
            .'<p>Your server data will be kept until <strong>{scheduled_deletion_at}</strong>. After that, the server is permanently deleted and the data cannot be recovered.</p>'
            .'<p>To keep your server, reactivate your subscription before that date — your data and configuration will be restored as-is.</p>',
            'Manage my account',
            '{panel_url}'
        );
    }

    public static function serverSuspendedFr(): string
    {
        return self::wrap(
            '<h1>Votre serveur {plan_name} a été suspendu</h1>'
            .'<p>Bonjour {name}, l\'abonnement de votre serveur <strong>{server_name}</strong> a été annulé et le serveur a été suspendu.</p>'
            .'<p>Les données de votre serveur sont conservées jusqu\'au <strong>{scheduled_deletion_at}</strong>. Au-delà, le serveur sera supprimé définitivement et les données ne pourront plus être récupérées.</p>'
            .'<p>Pour conserver votre serveur, réactivez votre abonnement avant cette date — vos données et configuration seront restaurées telles quelles.</p>',
            'Gérer mon compte',
            '{panel_url}'
        );
    }

    /**
     * Bridge-flavored body wrapper — green primary action button + a tiny
     * timestamp footer. No IP/UA block (these are not security alerts).
     */
    private static function wrap(string $bodyLead, string $ctaLabel, string $ctaUrlVariable): string
    {
        return $bodyLead."\n\n"
            .'<p style="text-align:center;margin:28px 0;">'
            .'<a href="'.$ctaUrlVariable.'" style="display:inline-block;padding:12px 28px;background:#16a34a;color:#fff;text-decoration:none;font-weight:600;border-radius:8px;">'
            .$ctaLabel.'</a>'
            .'</p>'
            .'<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">'
            .'<p style="font-size:12px;color:#9ca3af;text-align:center;">{timestamp}</p>';
    }
}
