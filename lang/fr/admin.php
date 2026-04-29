<?php

return [
    'navigation' => [
        'groups' => [
            'servers' => 'Serveurs & Pelican',
            'integrations' => 'Intégrations & Logs',
            'settings' => 'Paramètres',
        ],
        'player_panel' => 'Panneau joueur',
    ],

    'resources' => [
        'users' => [
            'label' => 'Utilisateur',
            'plural' => 'Utilisateurs',
            'navigation' => 'Utilisateurs',
        ],
        'servers' => [
            'label' => 'Serveur',
            'plural' => 'Serveurs',
            'navigation' => 'Serveurs',
        ],
        'server_plans' => [
            'label' => 'Plan',
            'plural' => 'Plans',
            'navigation' => 'Plans',
        ],
        'eggs' => [
            'label' => 'Egg',
            'plural' => 'Eggs',
            'navigation' => 'Eggs',
        ],
        'nodes' => [
            'label' => 'Node',
            'plural' => 'Nodes',
            'navigation' => 'Nodes',
        ],
        'pelican_webhook_logs' => [
            'label' => 'Webhook',
            'plural' => 'Webhooks reçus',
            'navigation' => 'Webhooks reçus',
        ],
        'bridge_sync_logs' => [
            'label' => 'Sync Bridge',
            'plural' => 'Logs Bridge',
            'navigation' => 'Logs Bridge',
        ],
        'sync_logs' => [
            'label' => 'Sync',
            'plural' => 'Logs de sync',
            'navigation' => 'Logs de sync',
        ],
        'pelican_backups' => [
            'label' => 'Backup',
            'plural' => 'Backups',
            'navigation' => 'Backups',
        ],
        'pelican_allocations' => [
            'label' => 'Allocation',
            'plural' => 'Allocations',
            'navigation' => 'Allocations',
        ],
        'pelican_server_transfers' => [
            'label' => 'Transfert',
            'plural' => 'Transferts',
            'navigation' => 'Transferts',
        ],
    ],

    'pages' => [
        'settings' => [
            'title' => 'Paramètres',
            'navigation' => 'Paramètres',
            'subtitle' => 'Configuration générale du panneau.',
        ],
        'auth_settings' => [
            'title' => 'Authentification & Sécurité',
            'navigation' => 'Auth & Sécurité',
            'subtitle' => 'Fournisseurs de connexion, 2FA, auth sociale.',
        ],
        'bridge_settings' => [
            'title' => 'Bridge',
            'navigation' => 'Bridge',
            'subtitle' => 'Reliez votre shop ou Paymenter pour provisionner les serveurs automatiquement.',
        ],
        'theme_settings' => [
            'title' => 'Thème',
            'navigation' => 'Thème',
            'subtitle' => 'Identité visuelle, couleurs, typographie, mise en page.',
        ],
        'email_templates' => [
            'title' => 'Templates d\'emails',
            'navigation' => 'Emails',
            'subtitle' => 'Personnalisez les emails transactionnels du panneau.',
        ],
        'plugins' => [
            'title' => 'Plugins',
            'navigation' => 'Plugins',
            'subtitle' => 'Étendez Peregrine avec des plugins officiels et communautaires.',
        ],
        'pelican_webhook_settings' => [
            'title' => 'Récepteur webhooks',
            'navigation' => 'Récepteur webhooks',
            'subtitle' => 'Configurez le token utilisé par Pelican pour pousser ses events.',
        ],
        'about' => [
            'title' => 'À propos',
            'navigation' => 'À propos',
            'subtitle' => 'Version, environnement, statut de mise à jour.',
        ],
    ],

    'widgets' => [
        'stats' => [
            'users' => 'Utilisateurs',
            'servers' => 'Serveurs',
            'active_servers' => 'Serveurs actifs',
            'pending_jobs' => 'Jobs en attente',
            'eggs' => 'Eggs synchronisés',
        ],
        'recent_servers' => 'Serveurs récents',
        'recent_webhooks' => 'Webhooks récents',
        'system_health' => [
            'title' => 'État du système',
            'queue_worker' => 'Worker queue',
            'last_sync' => 'Dernière sync Pelican',
            'bridge_mode' => 'Mode Bridge',
            'cache' => 'Cache',
            'never' => 'Jamais',
            'healthy' => 'OK',
            'stale' => 'Obsolète',
            'down' => 'En panne',
        ],
    ],

    'common' => [
        'view_payload' => 'Voir le payload',
        'payload_modal_title' => 'Payload',
        'copy' => 'Copier',
        'system_managed' => 'Géré par Pelican',
        'shop_managed' => 'Géré par le Shop',
        'paymenter_managed' => 'Géré par Paymenter',
        'auto' => 'Auto',
        'not_configured' => 'Non configuré',
        'empty_states' => [
            'servers' => 'Aucun serveur. Synchronisez depuis Pelican ou attendez une commande.',
            'users' => 'Aucun utilisateur pour l\'instant.',
            'plans' => 'Aucun plan. Poussez-les depuis votre shop.',
            'eggs' => 'Aucun egg synchronisé. Lancez une sync depuis la page Pelican.',
            'nodes' => 'Aucun node synchronisé.',
            'logs' => 'Aucune entrée de log pour l\'instant.',
        ],
    ],

    'badges' => [
        'bridge_mode' => [
            'disabled' => 'Bridge désactivé',
            'shop_stripe' => 'Shop + Stripe',
            'paymenter' => 'Paymenter',
        ],
    ],

    'tabs' => [
        'identity' => 'Identité',
        'configuration' => 'Configuration',
        'billing' => 'Facturation',
        'provisioning' => 'Provisioning',
        'shop_metadata' => 'Métadonnées Shop',
        'peregrine_config' => 'Configuration Peregrine',
        'pelican_link' => 'Lien Pelican',
        'stripe_link' => 'Lien Stripe',
        'oauth_link' => 'Identités OAuth',
        'colors' => 'Couleurs',
        'typography' => 'Typographie',
        'density' => 'Densité',
        'cards' => 'Cartes',
        'sidebar' => 'Barre latérale',
    ],
];
