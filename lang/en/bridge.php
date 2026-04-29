<?php

return [
    'webhook' => [
        'received' => 'Webhook received successfully.',
        'invalid_signature' => 'Invalid webhook signature.',
        'processing' => 'Webhook is being processed.',
    ],
    'provisioning' => [
        'started' => 'Server provisioning started.',
        'completed' => 'Server provisioning completed.',
        'failed' => 'Server provisioning failed.',
    ],
    'subscription' => [
        'updated' => 'Subscription updated.',
        'cancelled' => 'Subscription cancelled.',
        'expired' => 'Subscription expired. Server suspended.',
    ],
    'errors' => [
        'disabled' => 'The Bridge API is disabled.',
        'secret_not_configured' => 'The Bridge shared secret has not been configured.',
        'invalid_signature' => 'Invalid HMAC signature.',
        'invalid_timestamp' => 'Missing or malformed X-Bridge-Timestamp header.',
        'timestamp_expired' => 'Request timestamp is outside the 5-minute replay window.',
        'plan_not_found' => 'No plan with the given shop_plan_id was found.',
    ],
    'plan' => [
        'status' => [
            'ready' => 'Ready',
            'needs_config' => 'Needs config',
            'inactive' => 'Inactive',
            'sync_error' => 'Sync error',
        ],
    ],
    'settings' => [
        'enabled_label' => 'Enable Bridge API',
        'shop_url_label' => 'Shop base URL',
        'shared_secret_label' => 'Shared HMAC secret',
        'generate_secret' => 'Generate new secret',
        'saved' => 'Bridge settings saved',
    ],
    'pelican' => [
        'events' => [
            'server_created' => 'Server created in Pelican',
            'server_updated' => 'Server updated in Pelican',
            'server_deleted' => 'Server deleted in Pelican',
            'server_installed' => 'Server install completed',
            'user_created' => 'User created in Pelican',
            'user_updated' => 'User profile updated in Pelican',
            'user_deleted' => 'User removed from Pelican',
            'node_created' => 'Node added in Pelican',
            'node_updated' => 'Node updated in Pelican',
            'node_deleted' => 'Node removed from Pelican',
            'egg_created' => 'Egg added in Pelican',
            'egg_updated' => 'Egg updated in Pelican',
            'egg_deleted' => 'Egg removed from Pelican',
            'egg_variable_created' => 'Egg variable added',
            'egg_variable_updated' => 'Egg variable updated',
            'egg_variable_deleted' => 'Egg variable removed',
            'ignored' => 'Unsupported event (recorded for audit)',
        ],
        'stuck_provisioning_badge' => 'Stuck (webhook missing)',
        'stuck_provisioning_tooltip' => 'This server has been awaiting the Pelican install-completion webhook for over 30 minutes. Most likely the events :updated_server: and :server_installed: are not ticked in your Pelican /admin/webhooks. Check /admin/pelican-webhook-logs for incoming events and /docs/pelican-webhook for the configuration guide.',
    ],
];
