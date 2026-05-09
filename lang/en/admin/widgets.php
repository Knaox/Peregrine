<?php

return [
    'stats' => [
        'users' => 'Users',
        'servers' => 'Servers',
        'active_servers' => 'Active servers',
        'pending_jobs' => 'Pending jobs',
        'eggs' => 'Synced eggs',
        'jobs_queued' => ':n queued',
    ],
    'recent_servers' => 'Recent servers',
    'recent_webhooks' => 'Recent webhooks',
    'system_health' => [
        'title' => 'System health',
        'queue_worker' => 'Queue worker',
        'last_sync' => 'Last Pelican sync',
        'bridge_mode' => 'Bridge mode',
        'integrations' => [
            'label' => 'Integrations',
            'none' => 'None configured',
            'stripe_only' => 'Stripe webhook configured',
            'shops_only' => ':count active shop(s)',
            'stripe_and_shops' => 'Stripe + :count active shop(s)',
        ],
        'cache' => 'Cache',
        'never' => 'Never',
        'healthy' => 'Healthy',
        'stale' => 'Stale',
        'down' => 'Down',
    ],
];
