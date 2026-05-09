<?php

return [
    'stats' => [
        'users' => 'Utilisateurs',
        'servers' => 'Serveurs',
        'active_servers' => 'Serveurs actifs',
        'pending_jobs' => 'Jobs en attente',
        'eggs' => 'Eggs synchronisés',
        'jobs_queued' => ':n en queue',
    ],
    'recent_servers' => 'Serveurs récents',
    'recent_webhooks' => 'Webhooks récents',
    'system_health' => [
        'title' => 'État du système',
        'queue_worker' => 'Worker queue',
        'last_sync' => 'Dernière sync Pelican',
        'bridge_mode' => 'Mode Bridge',
        'integrations' => [
            'label' => 'Intégrations',
            'none' => 'Aucune configurée',
            'stripe_only' => 'Webhook Stripe configuré',
            'shops_only' => ':count shop(s) actif(s)',
            'stripe_and_shops' => 'Stripe + :count shop(s) actif(s)',
        ],
        'cache' => 'Cache',
        'never' => 'Jamais',
        'healthy' => 'OK',
        'stale' => 'Obsolète',
        'down' => 'En panne',
    ],
];
