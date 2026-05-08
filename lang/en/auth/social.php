<?php

return [
    'providers' => [
        'shop' => 'Shop',
        'google' => 'Google',
        'discord' => 'Discord',
        'linkedin' => 'LinkedIn',
        'paymenter' => 'Paymenter',
    ],
    'email_not_verified' => 'This email has not been verified by the provider.',
    'register_on_canonical_first' => 'Please register on :provider first.',
    'cannot_unlink_last_method' => 'Cannot unlink your only sign-in method.',
    'provider_disabled' => 'This sign-in provider is not enabled.',
    'mail' => [
        'linked' => [
            'subject' => ':provider linked to your account',
            'body' => ':provider was just linked as a sign-in method on your account.',
        ],
        'unlinked' => [
            'subject' => ':provider unlinked from your account',
            'body' => ':provider was just removed as a sign-in method on your account.',
        ],
    ],
];
