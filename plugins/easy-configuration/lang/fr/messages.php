<?php

declare(strict_types=1);

/**
 * Traductions backend (Laravel) du plugin Easy Configuration.
 * Accès via `__('easy-configuration::messages.key')`. L'UI React joueur et
 * admin est traduite séparément sous frontend/i18n/.
 */
return [
    'name' => 'Configuration facile',

    'validation' => [
        'invalid_template' => 'Le JSON du template est invalide : :error',
        'unknown_format' => 'Format de fichier de configuration non supporté : :format',
    ],

    'import' => [
        'unsupported_format' => "Impossible de détecter le format depuis l'extension — choisis-en un explicitement.",
        'server_unavailable' => "Ce serveur n'est pas encore prêt (pas d'identifiant Pelican).",
        'read_failed' => "Impossible de lire ce fichier sur le serveur. Vérifie le chemin et que le serveur existe.",
        'list_failed' => "Impossible de lister ce dossier. Vérifie que le serveur est en ligne et accessible.",
    ],
];
