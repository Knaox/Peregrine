<?php

declare(strict_types=1);

return [
    'settings' => [
        'title' => 'Intégration phpMyAdmin',
        'save' => 'Enregistrer',
        'saved' => 'Réglages enregistrés.',
        'close' => 'Fermer',

        'section_connection' => 'Connexion',
        'enabled' => 'Activé',
        'enabled_help' => 'Affiche le bouton « Ouvrir dans phpMyAdmin » sur chaque base de chaque serveur.',
        'pma_url' => 'URL de phpMyAdmin',
        'pma_url_help' => 'L\'URL où vous accédez à phpMyAdmin dans votre navigateur (ex. http://192.168.1.30/phpmyadmin) — PAS l\'URL de Peregrine.',
        'shared_secret' => 'Secret partagé',
        'shared_secret_help' => 'Envoyé par votre SignonScript phpMyAdmin dans l\'en-tête X-Plugin-Secret. À recopier dans peregrine_signon.php.',
        'regenerate' => 'Régénérer le secret',
        'regenerate_warning' => 'Ceci invalide le secret actuel. phpMyAdmin refusera les connexions tant que peregrine_signon.php n\'est pas mis à jour avec la nouvelle valeur.',
        'regenerated' => 'Secret régénéré — enregistrez, puis mettez à jour peregrine_signon.php.',
        'token_ttl' => 'Durée de vie du token (secondes)',
        'token_ttl_help' => 'Durée de validité d\'un token signon avant utilisation (10–120 s, défaut 30).',
        'auto_login' => 'Connexion automatique',
        'auto_login_help' => 'Si activé, le bouton connecte l\'utilisateur automatiquement (signon). Si désactivé, le bouton ouvre phpMyAdmin et l\'utilisateur saisit ses identifiants (visibles via « Afficher le mot de passe » dans l\'onglet Bases).',
        'auto_select_db' => 'Pré-sélection de la base',
        'auto_select_db_help' => 'Pré-sélectionne la base cliquée dans phpMyAdmin via ?db=.',
        'server_index' => 'Index du serveur signon',
        'server_index_help' => 'L\'index $i du serveur signon dans config.inc.php. Laissez vide s\'il est votre seul serveur / le serveur par défaut ; renseignez-le (ex. 2) si phpMyAdmin a déjà un autre serveur, pour que l\'accès direct garde son login normal.',

        'section_security' => 'Sécurité (avancé)',
        'section_security_desc' => 'Durcissement optionnel de l\'endpoint public de redeem.',
        'ip_allowlist' => 'Liste d\'IP autorisées',
        'ip_allowlist_help' => 'Une IP ou un CIDR par ligne. Si renseigné, seules ces adresses peuvent utiliser un token (votre serveur phpMyAdmin). Vide = aucune restriction.',
        'rate_limit' => 'Lancements par minute par utilisateur',
        'rate_limit_help' => 'Nombre maximal de lancements phpMyAdmin déclenchables par minute et par utilisateur.',

        'guide' => 'Guide d\'installation',
        'copy' => 'Copier',
        'copied' => 'Copié !',
        'step' => 'Étape',
        'troubleshooting' => 'Dépannage',
        'test_curl' => 'Tester le pont (curl)',
        'test_curl_help' => 'À exécuter depuis votre serveur phpMyAdmin. Une réponse 200 avec des identifiants factices signifie que le pont de redeem fonctionne.',
        'test_reachability' => 'Tester la joignabilité',
        'reachability_no_url' => 'Renseignez d\'abord l\'URL de phpMyAdmin.',
        'reachability_ok' => 'L\'URL phpMyAdmin est joignable depuis Peregrine.',
        'reachability_fail' => 'Impossible de joindre l\'URL phpMyAdmin depuis Peregrine.',
    ],
];
