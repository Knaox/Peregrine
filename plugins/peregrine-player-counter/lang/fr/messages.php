<?php

declare(strict_types=1);

return [
    'settings' => [
        'title' => 'Compteur de joueurs',
        'save' => 'Enregistrer',
        'saved' => 'Réglages enregistrés.',
        'close' => 'Fermer',

        'section_connection' => 'Connexion',
        'enabled' => 'Activé',
        'enabled_help' => 'Affiche le nombre de joueurs connectés en direct (et jusqu\'à 5 noms) sur l\'aperçu de chaque serveur.',
        'sidecar_url' => 'URL du sidecar',
        'sidecar_url_help' => 'Où Peregrine joint le sidecar GameDig. Docker : http://game-query:9899 — bare-metal : http://127.0.0.1:9899. Voir le guide.',
        'sidecar_token' => 'Jeton partagé (optionnel)',
        'sidecar_token_help' => 'Si défini, Peregrine l\'envoie en jeton Bearer ; mettez la MÊME valeur en GAME_QUERY_TOKEN sur le sidecar. Laissez vide pour un sidecar en loopback uniquement.',
        'regenerate' => 'Générer un jeton',
        'regenerated' => 'Jeton généré — enregistrez, puis mettez la même valeur sur le sidecar.',

        'supported_title' => 'Détection automatique des jeux',
        'supported_intro' => 'Les :count jeux du catalogue GameDig sont détectés automatiquement depuis l\'egg, avec le bon protocole. Quand un jeu nécessite un port de requête dédié (Valheim, Sons of the Forest, ARK…), la carte du serveur propose un bouton « Résoudre » qui alloue le port et configure le serveur — jamais automatiquement.',
        'supported_note' => 'Les jeux hors catalogue tentent quand même une requête A2S best-effort. Utilisez la liste blanche d\'eggs ci-dessous pour choisir les serveurs où le compteur s\'affiche.',

        'section_visibility' => 'Visibilité',
        'egg_whitelist' => 'Eggs autorisés (liste blanche)',
        'egg_whitelist_help' => 'Parmi les jeux supportés ci-dessus, n\'affiche le compteur que sur les serveurs utilisant ces eggs. Laissez vide pour autoriser tous les eggs supportés.',

        'guide' => 'Guide d\'installation Docker',
        'copy' => 'Copier',
        'copied' => 'Copié !',
        'test' => 'Tester le sidecar',
        'test_no_url' => 'Renseignez d\'abord l\'URL du sidecar.',
        'test_ok' => 'Le sidecar est joignable depuis Peregrine.',
        'test_fail' => 'Impossible de joindre le sidecar depuis Peregrine.',
    ],
];
