<?php

declare(strict_types=1);

return [
    'navigation' => 'Modpack Installer',
    'title' => 'Modpack Installer',

    'curseforge' => [
        'section' => 'CurseForge',
        'description' => 'Required to enable the CurseForge provider. Get a key at console.curseforge.com.',
        'api_key' => [
            'label' => 'CurseForge API key',
            'placeholder' => 'Leave blank to keep the existing key',
        ],
    ],

    'eligibility' => [
        'section' => 'Eligible servers',
        'description' => 'Pick the eggs allowed to install modpacks. Until at least one is selected, the Modpacks tab stays hidden on every server.',
    ],

    'providers' => [
        'section' => 'Providers',
        'modrinth' => 'Modrinth',
        'curseforge' => 'CurseForge',
        'atlauncher' => 'ATLauncher',
        'ftb' => 'Feed The Beast',
        'technic' => 'Technic',
        'voidswrath' => 'VoidsWrath',
    ],

    'sort' => [
        'relevance' => 'Relevance',
        'downloads' => 'Most downloads',
        'updated' => 'Recently updated',
        'newest' => 'Newest',
    ],

    'display' => [
        'section' => 'Display',
    ],

    'behavior' => [
        'section' => 'Behavior',
    ],

    'fields' => [
        'egg_ids' => [
            'label' => 'Allowed eggs',
            'help' => 'Lists all eggs synced from Pelican into the local database. Pick the ones whose servers may install modpacks.',
        ],
        'default_provider' => [
            'label' => 'Default provider',
        ],
        'default_sort' => [
            'label' => 'Default sort',
            'help' => 'Initial sort order applied to the marketplace listing.',
        ],
        'page_label' => [
            'label' => 'Tab label override',
            'help' => 'Optional. Defaults to the translated "Modpacks" string.',
        ],
        'page_route' => [
            'label' => 'Tab route',
            'help' => 'URL suffix appended after /servers/{id}. Lower-case, dashes, leading slash. Default: /modpacks.',
        ],
        'modpacks_per_page' => [
            'label' => 'Modpacks per page',
            'help' => 'How many modpack cards the marketplace tab fetches per page.',
        ],
        'install_timeout_minutes' => [
            'label' => 'Install timeout (minutes)',
            'help' => 'Beyond this duration without progress, the install is auto-marked failed by the reconciler cron.',
        ],
        'cache_ttl_seconds' => [
            'label' => 'Provider cache TTL (seconds)',
            'help' => 'How long to cache provider responses (search hits, modpack metadata, version lists). 60 to 86400.',
        ],
    ],

    'actions' => [
        'save' => 'Save',
        'import_egg' => [
            'label' => 'Import egg into Pelican',
            'tooltip' => 'Pushes the bundled installer egg to Pelican. Pelican matches on UUID, so re-running is safe and idempotent.',
        ],
    ],

    'notifications' => [
        'saved' => 'Settings saved',
        'egg_imported' => 'Egg imported into Pelican (id: :id)',
        'egg_import_failed' => 'Egg import failed: :reason',
    ],
];
