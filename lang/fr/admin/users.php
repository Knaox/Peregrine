<?php

return [
    'resource' => [
        'label' => 'Utilisateur',
        'plural' => 'Utilisateurs',
        'navigation' => 'Utilisateurs',
    ],
    'sections' => [
        'identity' => 'Identité',
    ],
    'helpers' => [
        'password' => 'Minimum 8 caractères. Hashé via bcrypt avant stockage.',
        'admin' => 'Donne accès à /admin et étend le whitelist Gate::before (Server uniquement).',
        'pelican_user_id' => 'Override manuel — modifier re-mappe l\'utilisateur vers un autre compte Pelican sans toucher à Pelican.',
        'stripe_customer_id' => 'Défini automatiquement au premier checkout Stripe. À éditer uniquement pour corriger une incohérence.',
        'sync_pelican' => 'Si cet utilisateur est synchronisé, pousse aussi le nouveau mot de passe vers le compte Pelican.',
    ],
    'tooltips' => [
        'pelican_linked' => 'Lié à l\'utilisateur Pelican #:id',
        'pelican_unlinked' => 'Aucun compte Pelican — utilisez l\'action Lier pour en provisionner un.',
    ],
    'link_pelican' => [
        'label' => 'Lier à Pelican',
        'modal_heading' => 'Provisionner un compte Pelican',
        'modal_description' => 'Dispatche un job en arrière-plan qui cherche l\'utilisateur dans Pelican par email, ou en crée un si absent. Sûr à relancer — le job est idempotent.',
        'notification_title' => 'Job de liaison dispatché',
        'notification_body' => 'Job en queue pour :email. Rafraîchissez dans quelques secondes pour voir le statut.',
    ],
    'change_password' => [
        'label' => 'Changer le mot de passe',
        'sync_pelican' => 'Mettre à jour aussi sur Pelican',
        'success' => 'Mot de passe modifié',
        'partial_failure_title' => 'Mot de passe local mis à jour, sync Pelican échouée',
        'partial_failure_body' => 'Le mot de passe local a été modifié mais Pelican n\'a pas pu être mis à jour. Vérifiez les logs.',
    ],
    'sync' => [
        'label' => 'Synchroniser les utilisateurs',
        'modal_heading' => 'Synchroniser les utilisateurs depuis Pelican',
        'modal_description' => 'Récupère tous les utilisateurs depuis Pelican et importe les nouveaux dans Peregrine.',
        'no_new' => 'Aucun nouvel utilisateur trouvé',
        'imported' => ':count utilisateurs importés depuis Pelican',
    ],
    'back_to_list' => 'Retour à la liste',
];
