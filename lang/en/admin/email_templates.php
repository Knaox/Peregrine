<?php

return [
    'page' => [
        'title' => 'Email templates',
        'navigation' => 'Emails',
        'subtitle' => 'Customize transactional emails sent by the panel.',
    ],
    'form' => [
        'global_section' => 'Global',
        'global_description' => 'Settings applied to all emails sent by Peregrine.',
        'footer' => 'Footer text',
        'footer_helper' => 'Appears at the bottom of every email. Leave empty for default.',
        'invitation_en' => 'Invitation email — English',
        'invitation_en_description' => 'Template for server invitation emails (EN). Use variables: {inviter_name}, {server_name}, {permissions_list}, {accept_url}, {expires_at}, {app_name}.',
        'invitation_fr' => 'Invitation email — French',
        'invitation_fr_description' => 'Template sent to recipients with a French locale.',
        'subject' => 'Subject',
        'body' => 'Body (HTML)',
        'subject_en' => 'Subject (EN)',
        'subject_fr' => 'Subject (FR)',
        'body_en' => 'Body EN (HTML)',
        'body_fr' => 'Body FR (HTML)',
    ],
    'registry' => [
        'two_factor_enabled' => [
            'label' => '2FA enabled',
            'description' => 'Sent when a user activates two-factor authentication on their account.',
        ],
        'two_factor_disabled' => [
            'label' => '2FA disabled',
            'description' => 'Sent when a user turns off two-factor authentication.',
        ],
        'recovery_regenerated' => [
            'label' => 'Recovery codes regenerated',
            'description' => 'Sent when a user regenerates their 2FA recovery codes.',
        ],
        'oauth_linked' => [
            'label' => 'OAuth provider linked',
            'description' => 'Sent when a social provider (Google, Discord, LinkedIn, Shop) is linked to a user account.',
        ],
        'oauth_unlinked' => [
            'label' => 'OAuth provider unlinked',
            'description' => 'Sent when a social provider is removed from a user account.',
        ],
        'payment_confirmed' => [
            'label' => 'Payment confirmed',
            'description' => 'Sent immediately after a Stripe checkout succeeds — receipt-style mail telling the customer what they paid and that the server is being provisioned. Sent BEFORE the "server ready" mail.',
        ],
        'server_ready_local' => [
            'label' => 'Server ready (local account)',
            'description' => 'Sent when a Bridge-provisioned server is ready, for users with local email/password accounts. Includes a one-time signed link to set their password.',
        ],
        'server_ready_oauth' => [
            'label' => 'Server ready (OAuth account)',
            'description' => 'Sent when a Bridge-provisioned server is ready, for users who signed in via OAuth (Shop / Google / Discord / LinkedIn / Paymenter). No password reset link needed.',
        ],
        'server_installed' => [
            'label' => 'Server installed (playable)',
            'description' => 'Sent when Pelican finishes the install script and the server is actually playable. Counterpart to "Server ready" (which fires right after the local row is created — at that point the install hasn\'t started yet).',
        ],
        'server_reactivated' => [
            'label' => 'Server reactivated (resubscribe)',
            'description' => 'Sent when a previously-suspended server has been resurrected through a fresh Stripe checkout (resubscribe flow). Same server / same data — only the subscription is new.',
        ],
        'server_suspended' => [
            'label' => 'Server suspended (subscription cancelled)',
            'description' => 'Sent when a server is suspended after a Stripe subscription cancellation. Includes the hard-deletion date plus a re-checkout link (resubscribe_url) and a Customer Portal link (billing_portal_url, secondary).',
        ],
        'trial_will_end' => [
            'label' => 'Trial will end (J-3 reminder)',
            'description' => 'Sent 3 days before a free trial converts to a paid charge. Tells the customer the upcoming charge date so they can update their card or cancel in time. Triggered by Stripe customer.subscription.trial_will_end.',
        ],
    ],
];
