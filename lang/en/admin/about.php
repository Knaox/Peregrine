<?php

return [
    'page' => [
        'title' => 'About',
        'navigation' => 'About',
        'subtitle' => 'Version, environment, update status.',
    ],
    'installed_version' => 'Installed version',
    'latest_release' => 'Latest release',
    'view_on_github' => 'View on GitHub',
    'no_release' => 'No release published yet',
    'check_error' => 'Couldn\'t check for updates: :error',
    'dev_build_warning' => 'The upstream repository hasn\'t published a release yet — you\'re on a development build.',
    'update_available' => 'A newer version is available. Follow the commands below to upgrade.',
    'up_to_date' => 'You\'re running the latest version.',
    'check_again' => 'Check again',
    'docker_commands' => 'Docker update commands',
    'manual_commands' => 'Manual update commands',
    'commands_help' => 'Run these commands in order on the host machine.',
    'copy_command' => 'Copy command',
    'copy' => 'Copy',
    'copied' => 'Copied',
    'about_peregrine' => 'About Peregrine',
    'repository' => 'Repository',
    'license' => 'License',
    'license_value' => 'MIT — open source',
    'latest_release_date' => 'Latest release date',
    'install_mode' => 'Install mode',
    'bare_metal' => 'Bare metal / manual',
    'update_notification_title' => [
        'available' => 'Update available',
        'up_to_date' => 'Up to date',
    ],
    'update_notification_body' => [
        'available' => 'Version :version is available.',
        'up_to_date' => 'You\'re running the latest version (:version).',
    ],
    'commands' => [
        'docker_pull' => [
            'title' => 'Pull latest images and restart',
            'description' => 'Fetches the latest published images and recreates the running containers.',
        ],
        'docker_migrate' => [
            'title' => 'Run migrations inside the container',
            'description' => 'Applies any new database migrations shipped with this release.',
        ],
        'git_pull' => [
            'title' => 'Pull latest code',
            'description' => 'Fetches the latest source from the main branch.',
        ],
        'install_deps' => [
            'title' => 'Install PHP + JS dependencies',
            'description' => 'Installs any added composer/pnpm dependencies.',
        ],
        'build' => [
            'title' => 'Build frontend assets',
            'description' => 'Rebuilds the Vite bundle with production optimizations.',
        ],
        'migrate' => [
            'title' => 'Migrate database + refresh caches',
            'description' => 'Applies pending migrations and rebuilds config/route caches.',
        ],
    ],
];
