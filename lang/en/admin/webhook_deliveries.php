<?php

declare(strict_types=1);

return [
    'resource' => [
        'label' => 'Webhook delivery',
        'plural' => 'Webhook deliveries',
        'navigation' => 'Webhook deliveries',
    ],
    'fields' => [
        'event_type' => 'Event',
        'shop' => 'Shop',
        'endpoint' => 'Endpoint',
        'attempts' => 'Attempts',
    ],
    'actions' => [
        'replay' => 'Replay',
        'replay_dispatched' => 'Delivery replay dispatched.',
        'docs' => 'Documentation',
    ],
];
