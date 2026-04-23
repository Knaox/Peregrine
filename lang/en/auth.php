<?php

return [
    'login' => [
        'title' => 'Sign In',
        'button' => 'Sign In',
        'oauth_button' => 'Sign in with :provider',
        'email' => 'Email Address',
        'password' => 'Password',
        'remember' => 'Remember me',
        'forgot_password' => 'Forgot your password?',
    ],
    'register' => [
        'title' => 'Create Account',
        'button' => 'Create Account',
        'name' => 'Full Name',
        'email' => 'Email Address',
        'password' => 'Password',
        'password_confirmation' => 'Confirm Password',
    ],
    'logout' => 'Sign Out',
    'failed' => 'These credentials do not match our records.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'providers' => [
        'shop' => 'Shop',
        'google' => 'Google',
        'discord' => 'Discord',
        'linkedin' => 'LinkedIn',
        'paymenter' => 'Paymenter',
    ],
    'social' => [
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
    ],
    '2fa' => [
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
    ],
];
