<?php

return [
    'mode' => env('AUTH_MODE', 'local'),
    'oauth' => [
        'client_id' => env('OAUTH_CLIENT_ID'),
        'client_secret' => env('OAUTH_CLIENT_SECRET'),
        'authorize_url' => env('OAUTH_AUTHORIZE_URL'),
        'token_url' => env('OAUTH_TOKEN_URL'),
        'user_url' => env('OAUTH_USER_URL'),
    ],
];
