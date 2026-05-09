<?php

declare(strict_types=1);

return [
    'resource' => [
        'label' => 'Endpoint webhook',
        'plural' => 'Endpoints webhook',
        'navigation' => 'Endpoints webhook',
    ],
    'fields' => [
        'shop' => 'Shop',
        'name' => 'Nom',
        'url' => 'URL',
        'signing_secret' => 'Signing secret',
        'status' => 'Statut',
        'subscribed_events' => 'Événements souscrits',
        'max_retries' => 'Tentatives max',
        'timeout_seconds' => 'Timeout (s)',
        'consecutive_failures' => 'Échecs',
    ],
    'helpers' => [
        'signing_secret' => 'Le shop vérifie la signature des webhooks avec ce secret exact. À traiter comme un identifiant.',
    ],
    'status' => [
        'active' => 'Actif',
        'paused' => 'En pause',
        'disabled' => 'Désactivé',
    ],
    'actions' => [
        'send_test' => 'Envoyer un événement test',
        'test_done' => 'Événement test envoyé',
        'test_failed' => 'Échec de l\'événement test',
        'rotate' => 'Tourner le secret',
        'rotated_title' => 'Secret tourné',
        'rotated_body' => 'Nouveau signing secret (copiez maintenant, ne sera plus affiché) : :secret',
        'docs' => 'Documentation',
    ],
];
