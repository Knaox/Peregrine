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
    'version' => env('APP_VERSION', '1.0.0-alpha.22'),

    // Repository used by the update checker (Knaox/Peregrine by default).
    'update_repo' => env('UPDATE_REPO', 'Knaox/Peregrine'),

    // Marker set by Docker-based installs so the update page shows Docker commands.
    'docker' => env('DOCKER', false),

    'marketplace' => [
        'registry_url' => env('MARKETPLACE_REGISTRY_URL', 'https://raw.githubusercontent.com/Knaox/peregrine-plugins/main/registry.json'),
        'enabled' => env('MARKETPLACE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Manual plugin upload (.zip import)
    |--------------------------------------------------------------------------
    |
    | Hardening for the "drop a plugin zip here" feature on /admin/plugins.
    | Every value here is a defence layer — keep them conservative unless you
    | explicitly trust your admins to upload arbitrary archives.
    |
    | max_size            : Hard upload size cap (bytes)
    | max_entries         : Refuses zips with more entries than this (zip-bomb)
    | max_extracted_size  : Refuses zips whose total uncompressed size exceeds
    | max_compression_ratio : Refuses zips whose compression ratio exceeds
    |                       this multiplier (classic zip-bomb signature)
    | allowed_extensions  : Whitelist — anything else aborts the import
    | forbidden_paths     : Substrings that cause the entire import to abort
    */
    'plugin_upload' => [
        'enabled' => env('PLUGIN_UPLOAD_ENABLED', true),
        'max_size' => (int) env('PLUGIN_UPLOAD_MAX_SIZE', 20 * 1024 * 1024),
        'max_entries' => (int) env('PLUGIN_UPLOAD_MAX_ENTRIES', 500),
        'max_extracted_size' => (int) env('PLUGIN_UPLOAD_MAX_EXTRACTED', 100 * 1024 * 1024),
        'max_compression_ratio' => (int) env('PLUGIN_UPLOAD_MAX_RATIO', 100),
        'allowed_extensions' => [
            'php', 'blade.php', 'json', 'md', 'txt', 'css', 'scss', 'js',
            'mjs', 'ts', 'vue', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'gif',
            'ico', 'woff', 'woff2', 'ttf', 'otf', 'yml', 'yaml', 'lock',
            'env.example', 'gitignore', 'editorconfig', 'html', 'xml',
            // Conventional extensionless basenames every plugin is allowed
            // to ship at the root. Matched as exact basenames by guardEntries.
            'license', 'readme', 'changelog', 'authors', 'contributors',
            'copying', 'notice', 'codeowners',
        ],
        'forbidden_paths' => [
            '..', 'vendor/', 'node_modules/', '.git/', '.github/', '.idea/',
            '.vscode/', '.env', '.htaccess', 'php.ini',
        ],
        'allow_overwrite' => env('PLUGIN_UPLOAD_ALLOW_OVERWRITE', false),
    ],

    'pelican' => [
        'url' => env('PELICAN_URL'),
        'admin_api_key' => env('PELICAN_ADMIN_API_KEY'),
        'client_api_key' => env('PELICAN_CLIENT_API_KEY'),
    ],
];
