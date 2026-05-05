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
        'eggs' => [
            'label' => 'Allowed eggs',
            'helper' => 'Lists all eggs synced from Pelican into the local database.',
            'empty' => 'No egg synced yet. Run "Sync eggs" from the admin first.',
        ],
    ],

    'behavior' => [
        'section' => 'Behavior',
        'timeout' => [
            'label' => 'Install timeout (minutes)',
            'helper' => 'Beyond this duration without progress, the install is auto-marked failed by the reconciler cron.',
        ],
        'provider' => [
            'label' => 'Default provider',
        ],
    ],

    'providers' => [
        'modrinth' => 'Modrinth',
        'curseforge' => 'CurseForge',
        'atlauncher' => 'ATLauncher',
        'ftb' => 'Feed The Beast',
        'technic' => 'Technic',
        'voidswrath' => 'VoidsWrath',
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
