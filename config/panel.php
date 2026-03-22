<?php

return [
    'installed' => env('PANEL_INSTALLED', false),
    'pelican' => [
        'url' => env('PELICAN_URL'),
        'admin_api_key' => env('PELICAN_ADMIN_API_KEY'),
    ],
];
