<?php

return [
    'page' => [
        'title' => 'Templates d\'emails',
        'navigation' => 'Emails',
        'subtitle' => 'Personnalisez les emails transactionnels du panneau.',
    ],
    'form' => [
        'global_section' => 'Global',
        'global_description' => 'Paramètres appliqués à tous les emails envoyés par Peregrine.',
        'footer' => 'Texte du pied de page',
        'footer_helper' => 'Apparaît au bas de chaque email. Laisser vide pour la valeur par défaut.',
        'invitation_en' => 'Email d\'invitation — Anglais',
        'invitation_en_description' => 'Template pour les emails d\'invitation serveur (EN). Variables : {inviter_name}, {server_name}, {permissions_list}, {accept_url}, {expires_at}, {app_name}.',
        'invitation_fr' => 'Email d\'invitation — Français',
        'invitation_fr_description' => 'Template envoyé aux destinataires en locale française.',
        'subject' => 'Objet',
        'body' => 'Corps (HTML)',
        'subject_en' => 'Objet (EN)',
        'subject_fr' => 'Objet (FR)',
        'body_en' => 'Corps EN (HTML)',
        'body_fr' => 'Corps FR (HTML)',
    ],
    'registry' => [
        'two_factor_enabled' => [
            'label' => '2FA activée',
            'description' => 'Envoyé quand un utilisateur active l\'authentification à deux facteurs sur son compte.',
        ],
        'two_factor_disabled' => [
            'label' => '2FA désactivée',
            'description' => 'Envoyé quand un utilisateur désactive l\'authentification à deux facteurs.',
        ],
        'recovery_regenerated' => [
            'label' => 'Codes de récupération régénérés',
            'description' => 'Envoyé quand un utilisateur régénère ses codes de récupération 2FA.',
        ],
        'oauth_linked' => [
            'label' => 'Fournisseur OAuth lié',
            'description' => 'Envoyé quand un fournisseur social (Google, Discord, LinkedIn, Shop) est lié à un compte utilisateur.',
        ],
        'oauth_unlinked' => [
            'label' => 'Fournisseur OAuth délié',
            'description' => 'Envoyé quand un fournisseur social est retiré d\'un compte utilisateur.',
        ],
        'payment_confirmed' => [
            'label' => 'Paiement confirmé',
            'description' => 'Envoyé immédiatement après un checkout Stripe réussi — email type reçu indiquant au client ce qu\'il a payé et que le serveur est en cours de provisioning. Envoyé AVANT l\'email « serveur prêt ».',
        ],
        'server_ready_local' => [
            'label' => 'Serveur prêt (compte local)',
            'description' => 'Envoyé quand un serveur provisionné via Bridge est prêt, pour les utilisateurs avec un compte email/mot de passe local. Inclut un lien signé à usage unique pour définir leur mot de passe.',
        ],
        'server_ready_oauth' => [
            'label' => 'Serveur prêt (compte OAuth)',
            'description' => 'Envoyé quand un serveur provisionné via Bridge est prêt, pour les utilisateurs connectés via OAuth (Shop / Google / Discord / LinkedIn / Paymenter). Pas de lien de réinitialisation de mot de passe nécessaire.',
        ],
        'server_installed' => [
            'label' => 'Serveur installé (jouable)',
            'description' => 'Envoyé quand Pelican termine le script d\'installation et que le serveur est réellement jouable. Pendant de « Serveur prêt » (qui se déclenche dès la création de la ligne locale — à ce moment l\'installation n\'a pas encore commencé).',
        ],
        'server_reactivated' => [
            'label' => 'Serveur réactivé (re-abonnement)',
            'description' => 'Envoyé quand un serveur précédemment suspendu a été ressuscité via un nouveau checkout Stripe (flux de re-abonnement). Même serveur / mêmes données — seul l\'abonnement est nouveau.',
        ],
        'server_suspended' => [
            'label' => 'Serveur suspendu (abonnement annulé)',
            'description' => 'Envoyé quand un serveur est suspendu après une annulation d\'abonnement Stripe. Inclut la date de suppression définitive plus un lien de re-checkout (resubscribe_url) et un lien Customer Portal (billing_portal_url, secondaire).',
        ],
        'trial_will_end' => [
            'label' => 'Fin d\'essai (rappel J-3)',
            'description' => 'Envoyé 3 jours avant qu\'un essai gratuit se transforme en débit payant. Indique au client la date du prélèvement à venir pour qu\'il puisse mettre à jour sa carte ou annuler à temps. Déclenché par Stripe customer.subscription.trial_will_end.',
        ],
    ],
];
