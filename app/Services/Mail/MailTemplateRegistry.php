<?php

namespace App\Services\Mail;

use App\Services\Mail\MailTemplateRegistry\AuthMailBodies;
use App\Services\Mail\MailTemplateRegistry\BridgeMailBodies;

/**
 * Central registry for user-facing email templates whose subject/body are
 * editable from /admin/email-templates.
 *
 * Each entry declares:
 *   - id            stable identifier (used as the settings key suffix)
 *   - group         admin UI grouping (Auth, Bridge, …)
 *   - label         admin UI section title
 *   - variables     list of substitution tokens exposed to the admin
 *   - default_*     subject / body defaults per locale
 *
 * The MailTemplateService resolves any user-facing email through this list:
 * admin overrides in the settings table take precedence; otherwise the
 * defaults below are used.
 *
 * HTML body factories live in sibling classes under
 * `App\Services\Mail\MailTemplateRegistry\` (one per template group).
 */
final class MailTemplateRegistry
{
    public const AUTH_2FA_ENABLED = 'auth_2fa_enabled';

    public const AUTH_2FA_DISABLED = 'auth_2fa_disabled';

    public const AUTH_2FA_RECOVERY_REGENERATED = 'auth_2fa_recovery_regenerated';

    public const AUTH_SOCIAL_LINKED = 'auth_social_linked';

    public const AUTH_SOCIAL_UNLINKED = 'auth_social_unlinked';

    public const BRIDGE_PAYMENT_CONFIRMED = 'bridge_payment_confirmed';

    public const BRIDGE_SERVER_INSTALLED = 'bridge_server_installed';

    public const BRIDGE_SERVER_REACTIVATED = 'bridge_server_reactivated';

    public const BRIDGE_SERVER_READY_LOCAL = 'bridge_server_ready_local';

    public const BRIDGE_SERVER_READY_OAUTH = 'bridge_server_ready_oauth';

    public const BRIDGE_SERVER_SUSPENDED = 'bridge_server_suspended';

    public const BRIDGE_TRIAL_WILL_END = 'bridge_trial_will_end';

    /**
     * @return array<int, array{id: string, group: string, label: string, description: string, variables: array<int, string>, default_subject_en: string, default_subject_fr: string, default_body_en: string, default_body_fr: string}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => self::AUTH_2FA_ENABLED,
                'group' => 'Auth',
                'label' => __('admin.mail_registry.two_factor_enabled.label'),
                'description' => __('admin.mail_registry.two_factor_enabled.description'),
                'variables' => ['name', 'server_name', 'timestamp', 'ip', 'user_agent', 'manage_url'],
                'default_subject_en' => '2FA enabled on your {server_name} account',
                'default_subject_fr' => '2FA activée sur votre compte {server_name}',
                'default_body_en' => AuthMailBodies::twoFactorEnabledEn(),
                'default_body_fr' => AuthMailBodies::twoFactorEnabledFr(),
            ],
            [
                'id' => self::AUTH_2FA_DISABLED,
                'group' => 'Auth',
                'label' => __('admin.mail_registry.two_factor_disabled.label'),
                'description' => __('admin.mail_registry.two_factor_disabled.description'),
                'variables' => ['name', 'server_name', 'timestamp', 'ip', 'user_agent', 'manage_url'],
                'default_subject_en' => '2FA disabled on your {server_name} account',
                'default_subject_fr' => '2FA désactivée sur votre compte {server_name}',
                'default_body_en' => AuthMailBodies::twoFactorDisabledEn(),
                'default_body_fr' => AuthMailBodies::twoFactorDisabledFr(),
            ],
            [
                'id' => self::AUTH_2FA_RECOVERY_REGENERATED,
                'group' => 'Auth',
                'label' => __('admin.mail_registry.recovery_regenerated.label'),
                'description' => __('admin.mail_registry.recovery_regenerated.description'),
                'variables' => ['name', 'server_name', 'timestamp', 'ip', 'user_agent', 'manage_url'],
                'default_subject_en' => 'Your {server_name} recovery codes were regenerated',
                'default_subject_fr' => 'Vos codes de récupération {server_name} ont été régénérés',
                'default_body_en' => AuthMailBodies::recoveryRegeneratedEn(),
                'default_body_fr' => AuthMailBodies::recoveryRegeneratedFr(),
            ],
            [
                'id' => self::AUTH_SOCIAL_LINKED,
                'group' => 'Auth',
                'label' => __('admin.mail_registry.oauth_linked.label'),
                'description' => __('admin.mail_registry.oauth_linked.description'),
                'variables' => ['name', 'provider', 'server_name', 'timestamp', 'ip', 'user_agent', 'manage_url'],
                'default_subject_en' => '{provider} linked to your {server_name} account',
                'default_subject_fr' => '{provider} lié à votre compte {server_name}',
                'default_body_en' => AuthMailBodies::socialLinkedEn(),
                'default_body_fr' => AuthMailBodies::socialLinkedFr(),
            ],
            [
                'id' => self::AUTH_SOCIAL_UNLINKED,
                'group' => 'Auth',
                'label' => __('admin.mail_registry.oauth_unlinked.label'),
                'description' => __('admin.mail_registry.oauth_unlinked.description'),
                'variables' => ['name', 'provider', 'server_name', 'timestamp', 'ip', 'user_agent', 'manage_url'],
                'default_subject_en' => '{provider} unlinked from your {server_name} account',
                'default_subject_fr' => '{provider} délié de votre compte {server_name}',
                'default_body_en' => AuthMailBodies::socialUnlinkedEn(),
                'default_body_fr' => AuthMailBodies::socialUnlinkedFr(),
            ],
            [
                'id' => self::BRIDGE_PAYMENT_CONFIRMED,
                'group' => 'Bridge',
                'label' => __('admin.mail_registry.payment_confirmed.label'),
                'description' => __('admin.mail_registry.payment_confirmed.description'),
                'variables' => ['name', 'plan_name', 'amount', 'currency', 'payment_date', 'invoice_url', 'panel_url', 'billing_portal_url', 'timestamp'],
                'default_subject_en' => 'Payment confirmed — {plan_name}',
                'default_subject_fr' => 'Paiement confirmé — {plan_name}',
                'default_body_en' => BridgeMailBodies::paymentConfirmedEn(),
                'default_body_fr' => BridgeMailBodies::paymentConfirmedFr(),
            ],
            [
                'id' => self::BRIDGE_SERVER_READY_LOCAL,
                'group' => 'Bridge',
                'label' => __('admin.mail_registry.server_ready_local.label'),
                'description' => __('admin.mail_registry.server_ready_local.description'),
                'variables' => ['name', 'plan_name', 'server_name', 'ip_port', 'reset_password_url', 'panel_url', 'login_url', 'timestamp'],
                'default_subject_en' => 'Your {plan_name} server is ready',
                'default_subject_fr' => 'Votre serveur {plan_name} est prêt',
                'default_body_en' => BridgeMailBodies::serverReadyLocalEn(),
                'default_body_fr' => BridgeMailBodies::serverReadyLocalFr(),
            ],
            [
                'id' => self::BRIDGE_SERVER_READY_OAUTH,
                'group' => 'Bridge',
                'label' => __('admin.mail_registry.server_ready_oauth.label'),
                'description' => __('admin.mail_registry.server_ready_oauth.description'),
                'variables' => ['name', 'plan_name', 'server_name', 'ip_port', 'panel_url', 'login_url', 'timestamp'],
                'default_subject_en' => 'Your {plan_name} server is ready',
                'default_subject_fr' => 'Votre serveur {plan_name} est prêt',
                'default_body_en' => BridgeMailBodies::serverReadyOAuthEn(),
                'default_body_fr' => BridgeMailBodies::serverReadyOAuthFr(),
            ],
            [
                'id' => self::BRIDGE_SERVER_INSTALLED,
                'group' => 'Bridge',
                'label' => __('admin.mail_registry.server_installed.label'),
                'description' => __('admin.mail_registry.server_installed.description'),
                'variables' => ['name', 'plan_name', 'server_name', 'ip_port', 'panel_url', 'login_url', 'timestamp'],
                'default_subject_en' => 'Your {plan_name} server is now playable',
                'default_subject_fr' => 'Votre serveur {plan_name} est maintenant jouable',
                'default_body_en' => BridgeMailBodies::serverInstalledEn(),
                'default_body_fr' => BridgeMailBodies::serverInstalledFr(),
            ],
            [
                'id' => self::BRIDGE_SERVER_REACTIVATED,
                'group' => 'Bridge',
                'label' => __('admin.mail_registry.server_reactivated.label'),
                'description' => __('admin.mail_registry.server_reactivated.description'),
                'variables' => ['name', 'plan_name', 'server_name', 'ip_port', 'panel_url', 'login_url', 'timestamp'],
                'default_subject_en' => 'Welcome back — your {plan_name} server is online again',
                'default_subject_fr' => 'Bon retour — votre serveur {plan_name} est de nouveau en ligne',
                'default_body_en' => BridgeMailBodies::serverReactivatedEn(),
                'default_body_fr' => BridgeMailBodies::serverReactivatedFr(),
            ],
            [
                'id' => self::BRIDGE_SERVER_SUSPENDED,
                'group' => 'Bridge',
                'label' => __('admin.mail_registry.server_suspended.label'),
                'description' => __('admin.mail_registry.server_suspended.description'),
                'variables' => ['name', 'plan_name', 'server_name', 'scheduled_deletion_at', 'panel_url', 'resubscribe_url', 'billing_portal_url', 'timestamp'],
                'default_subject_en' => 'Your {plan_name} server has been suspended',
                'default_subject_fr' => 'Votre serveur {plan_name} a été suspendu',
                'default_body_en' => BridgeMailBodies::serverSuspendedEn(),
                'default_body_fr' => BridgeMailBodies::serverSuspendedFr(),
            ],
            [
                'id' => self::BRIDGE_TRIAL_WILL_END,
                'group' => 'Bridge',
                'label' => __('admin.mail_registry.trial_will_end.label'),
                'description' => __('admin.mail_registry.trial_will_end.description'),
                'variables' => ['name', 'plan_name', 'server_name', 'trial_ends_at', 'panel_url', 'billing_portal_url', 'timestamp'],
                'default_subject_en' => 'Your {plan_name} trial ends in 3 days',
                'default_subject_fr' => 'Votre essai {plan_name} se termine dans 3 jours',
                'default_body_en' => BridgeMailBodies::trialWillEndEn(),
                'default_body_fr' => BridgeMailBodies::trialWillEndFr(),
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
}
