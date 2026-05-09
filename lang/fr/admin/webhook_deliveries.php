<?php

declare(strict_types=1);

return [
    'resource' => [
        'label' => 'Livraison webhook',
        'plural' => 'Livraisons webhook',
        'navigation' => 'Livraisons webhook',
    ],
    'fields' => [
        'event_type' => 'Événement',
        'shop' => 'Shop',
        'endpoint' => 'Endpoint',
        'attempts' => 'Tentatives',
    ],
    'actions' => [
        'replay' => 'Relancer',
        'replay_dispatched' => 'Relance de la livraison en file.',
        'docs' => 'Documentation',
    ],
];
