<?php

declare(strict_types=1);

return [
    'page' => [
        'navigation' => 'Stripe',
        'title' => 'Stripe integration',
        'subtitle' => 'Stripe webhook secret, API key and customer-facing URLs. Multi-shop, Pelican webhooks and provisioning settings live on dedicated pages.',
    ],
    'docs_link' => 'Documentation',
    'sections' => [
        'info' => [
            'title' => 'Integration map',
            'description' => 'Where each piece of the puzzle is configured.',
        ],
        'inbound' => [
            'title' => 'Stripe inbound',
            'description' => 'Required for Peregrine to receive Stripe events (checkout completed, subscription updated, refunds, disputes…). Get the values from your Stripe Dashboard.',
        ],
        'customer' => [
            'title' => 'Customer-facing URLs',
            'description' => 'Used in the lifecycle emails Peregrine sends (server suspended, trial ending…) so the customer can manage their subscription.',
        ],
    ],
    'info' => [
        'multi_shop' => [
            'label' => 'Multi-shop',
            'body' => 'Third-party shops are managed (with their API keys + outbound webhooks) on the Shops admin page:',
        ],
        'pelican' => [
            'label' => 'Pelican webhooks',
            'body' => 'Pelican webhooks are always active and are configured independently:',
        ],
        'third_party' => [
            'label' => 'Third-party billing (WHMCS, Paymenter, …)',
            'body' => 'If your billing system creates servers directly via the Pelican API, point its outgoing webhooks to /api/pelican/webhook. Peregrine mirrors Pelican state regardless of any shop configuration.',
        ],
    ],
    'fields' => [
        'webhook_secret' => 'Stripe webhook signing secret',
        'api_secret' => 'Stripe API secret',
        'billing_portal_url' => 'Stripe Billing Portal fallback URL',
        'resubscribe_url' => 'Resubscribe URL template',
        'grace_period_days' => 'Grace period (days)',
    ],
    'helpers' => [
        'webhook_secret' => 'Used to verify the signature of inbound Stripe events. Without this, the /api/stripe/webhook endpoint rejects all calls. Leave empty to keep the existing value; type a fresh value to rotate.',
        'api_secret' => 'Used by Peregrine to call Stripe (e.g. fetch invoice URLs in receipt emails, create Customer Portal sessions). Optional — without it, emails fall back to the Billing Portal URL below.',
        'billing_portal_url' => 'Static fallback URL pointing at your Stripe Customer Portal. Used in lifecycle emails when the API session creation fails or no API secret is set.',
        'resubscribe_url' => 'Template applied when sending the "your server was suspended" email. Placeholders: {server_id}, {configuration}, {configuration_id}, {ts}, {signature}.',
        'grace_period_days' => 'Days kept between subscription cancellation and actual server deletion. The customer can resubscribe during this window. Default: 14 days.',
    ],
    'placeholders' => [
        'webhook_secret' => 'whsec_…',
        'api_secret' => 'sk_live_…',
    ],
    'badges' => [
        'webhook_configured' => 'Stripe webhook configured',
        'webhook_missing' => 'Stripe webhook missing',
        'shop_configured' => 'Active shop(s)',
        'shop_missing' => 'No active shop',
    ],
    'notifications' => [
        'saved' => 'Stripe settings saved.',
    ],
];
