<?php

return [
    'page' => [
        'title' => 'À propos',
        'navigation' => 'À propos',
        'subtitle' => 'Version, environnement, statut de mise à jour.',
    ],
    'installed_version' => 'Version installée',
    'latest_release' => 'Dernière version',
    'view_on_github' => 'Voir sur GitHub',
    'no_release' => 'Aucune release publiée',
    'check_error' => 'Impossible de vérifier les mises à jour : :error',
    'dev_build_warning' => 'Le dépôt amont n\'a pas encore publié de release — vous êtes sur une version de développement.',
    'update_available' => 'Une nouvelle version est disponible. Suivez les commandes ci-dessous pour mettre à jour.',
    'up_to_date' => 'Vous êtes sur la dernière version.',
    'check_again' => 'Vérifier à nouveau',
    'docker_commands' => 'Commandes de mise à jour Docker',
    'manual_commands' => 'Commandes de mise à jour manuelles',
    'commands_help' => 'Lancez ces commandes dans l\'ordre sur la machine hôte.',
    'copy_command' => 'Copier la commande',
    'copy' => 'Copier',
    'copied' => 'Copié',
    'about_peregrine' => 'À propos de Peregrine',
    'repository' => 'Dépôt',
    'license' => 'Licence',
    'license_value' => 'MIT — open source',
    'latest_release_date' => 'Date de la dernière release',
    'install_mode' => 'Mode d\'installation',
    'bare_metal' => 'Bare metal / manuel',
    'update_notification_title' => [
        'available' => 'Mise à jour disponible',
        'up_to_date' => 'À jour',
    ],
    'update_notification_body' => [
        'available' => 'La version :version est disponible.',
        'up_to_date' => 'Vous utilisez la dernière version (:version).',
    ],
    'commands' => [
        'docker_pull' => [
            'title' => 'Récupérer les dernières images et redémarrer',
            'description' => 'Récupère les images publiées les plus récentes et recrée les conteneurs en cours.',
        ],
        'docker_migrate' => [
            'title' => 'Lancer les migrations dans le conteneur',
            'description' => 'Applique les nouvelles migrations livrées avec cette release.',
        ],
        'git_pull' => [
            'title' => 'Récupérer le dernier code',
            'description' => 'Récupère les dernières sources depuis la branche main.',
        ],
        'install_deps' => [
            'title' => 'Installer les dépendances PHP + JS',
            'description' => 'Installe les dépendances composer/pnpm ajoutées.',
        ],
        'build' => [
            'title' => 'Builder les assets frontend',
            'description' => 'Reconstruit le bundle Vite avec les optimisations production.',
        ],
        'migrate' => [
            'title' => 'Migrer la DB + rafraîchir les caches',
            'description' => 'Applique les migrations en attente et reconstruit les caches config/route.',
        ],
    ],
];
