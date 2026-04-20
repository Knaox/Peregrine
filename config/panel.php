<?php

return [
    'installed' => env('PANEL_INSTALLED', false),

    // Shipped version of the Peregrine panel. The admin "Updates" page compares
    // this against the latest GitHub release to tell the admin if they need to
    // upgrade.
    'version' => env('APP_VERSION', '1.0.0-alpha.1'),

    // Repository used by the update checker (Knaox/Peregrine by default).
    'update_repo' => env('UPDATE_REPO', 'Knaox/Peregrine'),

    // Marker set by Docker-based installs so the update page shows Docker commands.
    'docker' => env('DOCKER', false),

    'marketplace' => [
        'registry_url' => env('MARKETPLACE_REGISTRY_URL', 'https://raw.githubusercontent.com/Knaox/peregrine-plugins/main/registry.json'),
        'enabled' => env('MARKETPLACE_ENABLED', true),
    ],

    'pelican' => [
        'url' => env('PELICAN_URL'),
        'admin_api_key' => env('PELICAN_ADMIN_API_KEY'),
        'client_api_key' => env('PELICAN_CLIENT_API_KEY'),
    ],
];
