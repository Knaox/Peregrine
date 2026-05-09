<?php

declare(strict_types=1);

return [
    'resource' => [
        'label' => 'Shop',
        'plural' => 'Shops',
        'navigation' => 'Shops',
    ],
    'fields' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'domain' => 'Domain',
        'status' => 'Status',
        'metadata' => 'Metadata (JSON)',
        'api_keys_count' => 'API keys',
        'configurations_count' => 'Configurations',
        'shop_external_id' => 'Shop-side ID',
        'visible' => 'Visible',
        'sort_order' => 'Order',
    ],
    'helpers' => [
        'slug' => 'Stable URL-safe identifier. Used in audit logs and Stripe metadata references.',
        'domain' => 'Optional, informational only. No auth derived from it.',
        'metadata' => 'Free-form JSON. Persisted as-is. Useful for cross-system tagging.',
    ],
    'status' => [
        'active' => 'Active',
        'suspended' => 'Suspended',
    ],
    'actions' => [
        'suspend' => 'Suspend',
        'resume' => 'Resume',
        'status_updated' => 'Shop status updated.',
        'docs' => 'Documentation',
    ],
];
