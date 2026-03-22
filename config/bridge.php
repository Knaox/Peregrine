<?php

return [
    'enabled' => env('BRIDGE_ENABLED', false),
    'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
