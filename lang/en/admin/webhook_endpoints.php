<?php

declare(strict_types=1);

return [
    'resource' => [
        'label' => 'Webhook endpoint',
        'plural' => 'Webhook endpoints',
        'navigation' => 'Webhook endpoints',
    ],
    'fields' => [
        'shop' => 'Shop',
        'name' => 'Name',
        'url' => 'URL',
        'signing_secret' => 'Signing secret',
        'status' => 'Status',
        'subscribed_events' => 'Subscribed events',
        'max_retries' => 'Max retries',
        'timeout_seconds' => 'Timeout (s)',
        'consecutive_failures' => 'Failures',
    ],
    'helpers' => [
        'signing_secret' => 'The shop verifies webhook signatures with this exact secret. Treat as a credential.',
    ],
    'status' => [
        'active' => 'Active',
        'paused' => 'Paused',
        'disabled' => 'Disabled',
    ],
    'actions' => [
        'send_test' => 'Send test event',
        'test_done' => 'Test event sent',
        'test_failed' => 'Test event failed',
        'rotate' => 'Rotate secret',
        'rotated_title' => 'Secret rotated',
        'rotated_body' => 'New signing secret (copy now, never displayed again): :secret',
        'docs' => 'Documentation',
    ],
];
