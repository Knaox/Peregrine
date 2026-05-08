<?php

return [
    'required_admin_setup' => '2FA is required for admin accounts. Please set it up before continuing.',
    'invalid_code' => 'That code is invalid or expired.',
    'invalid_password' => 'Password is incorrect.',
    'challenge_expired' => 'Your 2FA challenge has expired. Please sign in again.',
    'already_enabled' => '2FA is already enabled on this account.',
    'not_enabled' => '2FA is not enabled on this account.',
    'mail' => [
        'greeting' => 'Hi :name,',
        'meta' => [
            'timestamp' => 'Time: :time',
            'ip' => 'IP address: :ip',
            'user_agent' => 'User agent: :ua',
        ],
        'cta' => [
            'review_security' => 'Review security settings',
        ],
        'footer' => [
            'not_me' => 'If this wasn\'t you, contact your administrator immediately.',
        ],
        'enabled' => [
            'subject' => '2FA enabled on your account',
            'body' => 'Two-factor authentication was just turned on for your account.',
        ],
        'disabled' => [
            'subject' => '2FA disabled on your account',
            'body' => 'Two-factor authentication was just turned off for your account.',
        ],
        'recovery_regenerated' => [
            'subject' => 'Your recovery codes were regenerated',
            'body' => 'A new set of 2FA recovery codes was generated for your account. Previous codes no longer work.',
        ],
    ],
];
