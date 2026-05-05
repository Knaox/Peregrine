<?php

return [
    // True iff the panel has been bootstrapped via the Setup Wizard.
    //
    // Three sources, evaluated in order :
    //   1. PANEL_INSTALLED=true env (set by the wizard in storage/.env)
    //   2. storage/.installed sentinel file (mirror of #1, written by the
    //      Docker entrypoint and the wizard — survives env-var loss)
    //
    // The sentinel fallback fixes a Docker pitfall : docker-compose passes
    // every declared env var, even if empty, which prevents Laravel's
    // DotEnv loader from reading PANEL_INSTALLED from storage/.env.
    // Without the fallback, plugins (and any other code that gates on
    // `config('panel.installed')`) silently no-op.
    'installed' => env('PANEL_INSTALLED', false) === true
        || file_exists(storage_path('.installed')),

    // Shipped version of the Peregrine panel. The admin "Updates" page compares
    // this against the latest GitHub release to tell the admin if they need to
    // upgrade.
    'version' => env('APP_VERSION', '1.0.0-alpha.3'),

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
