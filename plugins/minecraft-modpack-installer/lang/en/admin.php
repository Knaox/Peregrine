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

    'java' => [
        'section' => 'Java compatibility',
        'description' => 'Controls how the installer picks the Java version and Docker image for each modpack. Every field is optional — leave blank to use the plugin defaults shipped in config/java-compatibility.php.',
        'default_java' => [
            'label' => 'Default Java',
            'placeholder' => 'Use plugin default (Java 17)',
            'help' => 'Used when no compatibility rule matches the modpack (unknown MC version, exotic loader). Overrides the value declared in the plugin config.',
        ],
        'images' => [
            'label' => 'Docker images per Java major',
            'key_label' => 'Java major',
            'value_label' => 'Docker image',
            'add' => 'Add image override',
            'help' => 'Per-key override of the bundled image map. Add an entry only for the Java majors you want to redirect (e.g. point java_21 at a private mirror); the others fall back to plugin defaults.',
        ],
        'rules' => [
            'label' => 'Compatibility rules',
            'help' => 'Rules are evaluated top-down — first match wins. Loader-specific rules MUST come before generic (no loader) ones. Setting any rule here REPLACES the entire bundled rule list. Leave the table empty to keep the plugin defaults.',
            'add' => 'Add rule',
            'fields' => [
                'loader' => 'Loader',
                'loader_any' => 'Any loader (Vanilla too)',
                'mc_min' => 'Min Minecraft version',
                'mc_max' => 'Max Minecraft version',
                'java' => 'Java major',
            ],
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
