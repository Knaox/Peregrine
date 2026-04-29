<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    // Stripe API server-side secret. Used by the Bridge webhook handler to
    // expand `line_items` on a `checkout.session.completed` event (Stripe
    // never includes them in the webhook payload). NOT the webhook signing
    // secret — that one lives in the `settings` table as
    // `bridge_stripe_webhook_secret` and is admin-editable.
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Socialite providers. Values are overridden at runtime by
    // AuthProviderRegistry::configureSocialite() from the `settings` table
    // (admin-editable). The defaults here are placeholders that keep package
    // discovery happy when a provider is disabled.
    'google' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],

    'discord' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],

    'linkedin-openid' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],

    // Custom canonical OAuth providers. Defaults are placeholders — the live
    // config is pushed at runtime by AuthProviderRegistry::configureSocialite()
    // from the `settings` table. Endpoint URLs are derived from the base_url
    // by SocialiteConfigurator::applyShop / applyPaymenter / applyWhmcs.
    'shop' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
        'authorize_url' => '',
        'token_url' => '',
        'user_url' => '',
    ],

    'paymenter' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
        'base_url' => '',
        'authorize_url' => '',
        'token_url' => '',
        'user_url' => '',
    ],

    'whmcs' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
        'base_url' => '',
        'authorize_url' => '',
        'token_url' => '',
        'user_url' => '',
    ],

];
