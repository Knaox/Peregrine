<?php

/**
 * Strings backend du plugin Invitations.
 *
 * Utilisés par les routes API + publiques pour renvoyer des erreurs
 * traduites au frontend sans propager la logique i18n dans le bundle
 * SPA. Chargés via `loadTranslationsFrom(__DIR__.'/../lang', 'invitations')`
 * dans InvitationsServiceProvider — le frontend peut les afficher
 * directement (déjà localisés par le middleware SetUserLocale) ou
 * utiliser le champ `error_code` pour traduire côté client.
 */

return [
    'errors' => [
        'invitation_not_found' => 'Invitation introuvable ou expirée.',
        'email_mismatch' => "L'adresse email ne correspond pas à l'invitation.",
        'already_accepted' => 'Cette invitation a déjà été acceptée.',
        'already_revoked' => 'Cette invitation a été révoquée.',
        'account_exists' => 'Un compte existe déjà avec cette adresse email. Veuillez vous connecter.',
        'self_invite' => 'Vous ne pouvez pas vous inviter vous-même.',
        'invalid_token' => 'Invitation introuvable.',
        'self_modify' => 'Vous ne pouvez pas modifier vos propres permissions.',
        'self_remove' => 'Vous ne pouvez pas vous retirer vous-même.',
        'accept_failed' => "Impossible d'accepter l'invitation pour le moment. Veuillez réessayer dans un instant.",
    ],
    'success' => [
        'invitation_sent' => 'Invitation envoyée.',
        'invitation_resent' => 'Invitation renvoyée.',
        'invitation_revoked' => 'Invitation révoquée.',
        'invitation_updated' => 'Invitation mise à jour.',
        'permissions_updated' => 'Permissions mises à jour.',
        'account_created_and_accepted' => 'Compte créé et invitation acceptée.',
    ],
];
