<?php

return [
    'navigation' => [
        'groups' => [
            'servers' => 'Servers',
            'pelican' => 'Pelican',
            'pelican_mirror' => 'Pelican Mirror',
            'settings' => 'Settings',
        ],
        'player_panel' => 'Player panel',
    ],

    'resources' => [
        'users' => [
            'label' => 'User',
            'plural' => 'Users',
            'navigation' => 'Users',
        ],
        'servers' => [
            'label' => 'Server',
            'plural' => 'Servers',
            'navigation' => 'Servers',
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
            'label' => 'Webhook log',
            'plural' => 'Webhook logs',
            'navigation' => 'Webhook logs',
        ],
        'bridge_sync_logs' => [
            'label' => 'Bridge sync log',
            'plural' => 'Bridge sync logs',
            'navigation' => 'Bridge sync logs',
        ],
        'sync_logs' => [
            'label' => 'Sync log',
            'plural' => 'Sync logs',
            'navigation' => 'Sync logs',
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
            'label' => 'Server transfer',
            'plural' => 'Server transfers',
            'navigation' => 'Server transfers',
        ],
    ],

    'pages' => [
        'settings' => [
            'title' => 'Settings',
            'navigation' => 'Settings',
            'subtitle' => 'General configuration of the panel.',
        ],
        'auth_settings' => [
            'title' => 'Authentication & Security',
            'navigation' => 'Auth & Security',
            'subtitle' => 'Sign-in providers, 2FA, social auth.',
        ],
        'bridge_settings' => [
            'title' => 'Bridge',
            'navigation' => 'Bridge',
            'subtitle' => 'Connect your shop or Paymenter to provision servers automatically.',
        ],
        'theme_settings' => [
            'title' => 'Theme',
            'navigation' => 'Theme',
            'subtitle' => 'Branding, colors, typography, layout.',
        ],
        'email_templates' => [
            'title' => 'Email templates',
            'navigation' => 'Email templates',
            'subtitle' => 'Customize transactional emails sent by the panel.',
        ],
        'plugins' => [
            'title' => 'Plugins',
            'navigation' => 'Plugins',
            'subtitle' => 'Extend Peregrine with first-party and community plugins.',
        ],
        'pelican_webhook_settings' => [
            'title' => 'Webhook receiver',
            'navigation' => 'Webhook receiver',
            'subtitle' => 'Configure the token Pelican uses to push events.',
        ],
        'about' => [
            'title' => 'About',
            'navigation' => 'About',
            'subtitle' => 'Version, environment, update status.',
        ],
    ],

    'widgets' => [
        'stats' => [
            'users' => 'Users',
            'servers' => 'Servers',
            'active_servers' => 'Active servers',
            'pending_jobs' => 'Pending jobs',
            'eggs' => 'Synced eggs',
        ],
        'recent_servers' => 'Recent servers',
        'recent_webhooks' => 'Recent webhooks',
        'system_health' => [
            'title' => 'System health',
            'queue_worker' => 'Queue worker',
            'last_sync' => 'Last Pelican sync',
            'bridge_mode' => 'Bridge mode',
            'cache' => 'Cache',
            'never' => 'Never',
            'healthy' => 'Healthy',
            'stale' => 'Stale',
            'down' => 'Down',
        ],
    ],

    'common' => [
        'view_payload' => 'View payload',
        'payload_modal_title' => 'Payload',
        'copy' => 'Copy',
        'system_managed' => 'Managed by Pelican',
        'shop_managed' => 'Managed by the Shop',
        'paymenter_managed' => 'Managed by Paymenter',
        'auto' => 'Auto',
        'not_configured' => 'Not configured',
        'empty_states' => [
            'servers' => 'No servers yet. Sync from Pelican or wait for an order.',
            'users' => 'No users yet.',
            'plans' => 'No plans yet. Push them from your shop.',
            'eggs' => 'No eggs synced yet. Run sync from the Pelican page.',
            'nodes' => 'No nodes synced yet.',
            'logs' => 'No log entries yet.',
        ],
    ],

    'badges' => [
        'bridge_mode' => [
            'disabled' => 'Bridge disabled',
            'shop_stripe' => 'Shop + Stripe',
            'paymenter' => 'Paymenter',
        ],
    ],

    'tabs' => [
        'identity' => 'Identity',
        'configuration' => 'Configuration',
        'billing' => 'Billing',
        'provisioning' => 'Provisioning',
        'shop_metadata' => 'Shop metadata',
        'peregrine_config' => 'Peregrine config',
        'pelican_link' => 'Pelican link',
        'stripe_link' => 'Stripe link',
        'oauth_link' => 'OAuth identities',
        'colors' => 'Colors',
        'typography' => 'Typography',
        'density' => 'Density',
        'cards' => 'Cards',
        'sidebar' => 'Sidebar',
    ],
];
