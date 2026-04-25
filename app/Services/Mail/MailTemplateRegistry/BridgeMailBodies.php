<?php

namespace App\Services\Mail\MailTemplateRegistry;

/**
 * HTML body factories for the "Bridge" group of email templates
 * (server ready local / OAuth, server suspended).
 *
 * Distinct from `AuthMailBodies` because these are commercial mails — no
 * IP/UA forensic block, no "review security" CTA.
 *
 * Layout convention :
 *   - heading + lead paragraph
 *   - "card" block (server address, deletion date, …) on a tinted background
 *   - primary CTA button (green for ready, amber for suspended)
 *   - secondary text link below the CTA (login URL)
 *   - timestamp footer
 *
 * Extracted from `MailTemplateRegistry` to honour the 300-line file rule.
 */
final class BridgeMailBodies
{
    public static function paymentConfirmedEn(): string
    {
        return self::heading('✅ Payment confirmed')
            .'<p>Hi <strong>{name}</strong>, thanks for your purchase! Your payment for <strong>{plan_name}</strong> has been received.</p>'
            .self::addressCard('Amount paid', '{amount} {currency}')
            .'<p>Your server is being provisioned right now — you\'ll receive another email as soon as it\'s ready to use (usually under a minute).</p>'
            .self::buttonRow('{invoice_url}', 'View my invoice', color: '#3b82f6')
            .self::secondaryLink('Manage my subscription', '{billing_portal_url}')
            .self::timestamp();
    }

    public static function paymentConfirmedFr(): string
    {
        return self::heading('✅ Paiement confirmé')
            .'<p>Bonjour <strong>{name}</strong>, merci pour votre achat ! Votre paiement pour <strong>{plan_name}</strong> a bien été reçu.</p>'
            .self::addressCard('Montant payé', '{amount} {currency}')
            .'<p>Votre serveur est en cours de provisionnement — vous recevrez un autre email dès qu\'il sera prêt à l\'emploi (généralement en moins d\'une minute).</p>'
            .self::buttonRow('{invoice_url}', 'Voir ma facture', color: '#3b82f6')
            .self::secondaryLink('Gérer mon abonnement', '{billing_portal_url}')
            .self::timestamp();
    }

    public static function serverReadyLocalEn(): string
    {
        return self::heading('🎮 Your {plan_name} server is ready')
            .'<p>Hi <strong>{name}</strong>, your <strong>{server_name}</strong> server has been provisioned and is good to go.</p>'
            .self::addressCard('Server address', '{ip_port}')
            .'<p>You don\'t have a password yet — set one before signing in:</p>'
            .self::buttonRow('{reset_password_url}', 'Set my password', color: '#3b82f6')
            .'<p style="font-size:12px;color:#6b7280;text-align:center;margin:-12px 0 24px;">This link is valid for 7 days.</p>'
            .self::buttonRow('{panel_url}', 'Open my server', color: '#16a34a')
            .self::secondaryLink('Or sign in to the panel', '{login_url}')
            .self::timestamp();
    }

    public static function serverReadyLocalFr(): string
    {
        return self::heading('🎮 Votre serveur {plan_name} est prêt')
            .'<p>Bonjour <strong>{name}</strong>, votre serveur <strong>{server_name}</strong> a été provisionné et est prêt à l\'emploi.</p>'
            .self::addressCard('Adresse du serveur', '{ip_port}')
            .'<p>Vous n\'avez pas encore de mot de passe — définissez-en un avant de vous connecter :</p>'
            .self::buttonRow('{reset_password_url}', 'Définir mon mot de passe', color: '#3b82f6')
            .'<p style="font-size:12px;color:#6b7280;text-align:center;margin:-12px 0 24px;">Ce lien est valide pendant 7 jours.</p>'
            .self::buttonRow('{panel_url}', 'Accéder à mon serveur', color: '#16a34a')
            .self::secondaryLink('Ou se connecter au panel', '{login_url}')
            .self::timestamp();
    }

    public static function serverReadyOAuthEn(): string
    {
        return self::heading('🎮 Your {plan_name} server is ready')
            .'<p>Hi <strong>{name}</strong>, your <strong>{server_name}</strong> server has been provisioned and is good to go.</p>'
            .self::addressCard('Server address', '{ip_port}')
            .'<p>Sign in with your usual provider — no password needed.</p>'
            .self::buttonRow('{panel_url}', 'Open my server', color: '#16a34a')
            .self::secondaryLink('Or sign in to the panel', '{login_url}')
            .self::timestamp();
    }

    public static function serverReadyOAuthFr(): string
    {
        return self::heading('🎮 Votre serveur {plan_name} est prêt')
            .'<p>Bonjour <strong>{name}</strong>, votre serveur <strong>{server_name}</strong> a été provisionné et est prêt à l\'emploi.</p>'
            .self::addressCard('Adresse du serveur', '{ip_port}')
            .'<p>Connectez-vous avec votre fournisseur habituel — aucun mot de passe nécessaire.</p>'
            .self::buttonRow('{panel_url}', 'Accéder à mon serveur', color: '#16a34a')
            .self::secondaryLink('Ou se connecter au panel', '{login_url}')
            .self::timestamp();
    }

    public static function serverInstalledEn(): string
    {
        return self::heading('🚀 Your {plan_name} server is now playable')
            .'<p>Hi <strong>{name}</strong>, the install script just finished — your <strong>{server_name}</strong> server is up and ready for players.</p>'
            .self::addressCard('Server address', '{ip_port}')
            .'<p>Connect with the address above, or open the panel to manage the server, view the console, edit files, and more.</p>'
            .self::buttonRow('{panel_url}', 'Open my server', color: '#16a34a')
            .self::secondaryLink('Or sign in to the panel', '{login_url}')
            .self::timestamp();
    }

    public static function serverInstalledFr(): string
    {
        return self::heading('🚀 Votre serveur {plan_name} est maintenant jouable')
            .'<p>Bonjour <strong>{name}</strong>, le script d\'installation vient de se terminer — votre serveur <strong>{server_name}</strong> est en ligne et prêt pour les joueurs.</p>'
            .self::addressCard('Adresse du serveur', '{ip_port}')
            .'<p>Connectez-vous avec l\'adresse ci-dessus, ou ouvrez le panel pour gérer le serveur, voir la console, éditer les fichiers, etc.</p>'
            .self::buttonRow('{panel_url}', 'Accéder à mon serveur', color: '#16a34a')
            .self::secondaryLink('Ou se connecter au panel', '{login_url}')
            .self::timestamp();
    }

    public static function serverReactivatedEn(): string
    {
        return self::heading('🎉 Welcome back — your {plan_name} server is online again')
            .'<p>Hi <strong>{name}</strong>, your subscription has been renewed and your <strong>{server_name}</strong> server is back online with all your data intact.</p>'
            .self::addressCard('Server address', '{ip_port}')
            .'<p>Connect right where you left off — no install needed, the world / config / files are exactly as you left them.</p>'
            .self::buttonRow('{panel_url}', 'Open my server', color: '#16a34a')
            .self::secondaryLink('Or sign in to the panel', '{login_url}')
            .self::timestamp();
    }

    public static function serverReactivatedFr(): string
    {
        return self::heading('🎉 Bon retour — votre serveur {plan_name} est de nouveau en ligne')
            .'<p>Bonjour <strong>{name}</strong>, votre abonnement a été renouvelé et votre serveur <strong>{server_name}</strong> est de nouveau en ligne avec toutes vos données intactes.</p>'
            .self::addressCard('Adresse du serveur', '{ip_port}')
            .'<p>Reconnectez-vous exactement là où vous vous étiez arrêté — aucune installation nécessaire, le monde / les configs / les fichiers sont tels que vous les aviez laissés.</p>'
            .self::buttonRow('{panel_url}', 'Accéder à mon serveur', color: '#16a34a')
            .self::secondaryLink('Ou se connecter au panel', '{login_url}')
            .self::timestamp();
    }

    public static function serverSuspendedEn(): string
    {
        return self::heading('Your {plan_name} server has been suspended')
            .'<p>Hi <strong>{name}</strong>, the subscription for your <strong>{server_name}</strong> server was cancelled and the server has been suspended.</p>'
            .self::addressCard('Data kept until', '{scheduled_deletion_at}', tint: '#fef3c7')
            .'<p>After that date, the server is permanently deleted and the data cannot be recovered. Subscribe again before then to restore everything as-is.</p>'
            .self::buttonRow('{resubscribe_url}', 'Resubscribe to {plan_name}', color: '#f59e0b')
            .self::secondaryLink('Manage payment methods & invoices', '{billing_portal_url}')
            .self::secondaryLink('Or open the panel', '{panel_url}')
            .self::timestamp();
    }

    public static function serverSuspendedFr(): string
    {
        return self::heading('Votre serveur {plan_name} a été suspendu')
            .'<p>Bonjour <strong>{name}</strong>, l\'abonnement de votre serveur <strong>{server_name}</strong> a été annulé et le serveur a été suspendu.</p>'
            .self::addressCard('Données conservées jusqu\'au', '{scheduled_deletion_at}', tint: '#fef3c7')
            .'<p>Au-delà de cette date, le serveur est supprimé définitivement et les données ne peuvent plus être récupérées. Réabonnez-vous avant cette date pour tout restaurer en l\'état.</p>'
            .self::buttonRow('{resubscribe_url}', 'Me réabonner à {plan_name}', color: '#f59e0b')
            .self::secondaryLink('Gérer mes moyens de paiement & factures', '{billing_portal_url}')
            .self::secondaryLink('Ou ouvrir le panel', '{panel_url}')
            .self::timestamp();
    }

    private static function heading(string $text): string
    {
        return '<h1 style="margin:0 0 16px;font-size:22px;color:#111827;">'.$text.'</h1>';
    }

    private static function addressCard(string $label, string $value, string $tint = '#f3f4f6'): string
    {
        return '<div style="background:'.$tint.';padding:16px 20px;border-radius:10px;margin:20px 0;">'
            .'<div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;font-weight:600;margin-bottom:6px;">'.$label.'</div>'
            .'<div style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:16px;font-weight:600;color:#111827;">'.$value.'</div>'
            .'</div>';
    }

    private static function buttonRow(string $url, string $label, string $color): string
    {
        return '<p style="text-align:center;margin:24px 0;">'
            .'<a href="'.$url.'" style="display:inline-block;padding:12px 28px;background:'.$color.';color:#fff;text-decoration:none;font-weight:600;border-radius:8px;">'
            .$label.'</a>'
            .'</p>';
    }

    private static function secondaryLink(string $label, string $url): string
    {
        return '<p style="text-align:center;margin:8px 0 24px;font-size:13px;color:#6b7280;">'
            .$label.' : '
            .'<a href="'.$url.'" style="color:#3b82f6;text-decoration:none;font-weight:500;">'.$url.'</a>'
            .'</p>';
    }

    private static function timestamp(): string
    {
        return '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">'
            .'<p style="font-size:12px;color:#9ca3af;text-align:center;margin:0;">{timestamp}</p>';
    }
}
