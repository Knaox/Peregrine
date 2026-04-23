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
];
