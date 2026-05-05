<?php

declare(strict_types=1);

return [
    'navigation' => 'Installateur de modpacks',
    'title' => 'Installateur de modpacks',

    'curseforge' => [
        'section' => 'CurseForge',
        'description' => 'Requis pour activer le fournisseur CurseForge. Récupérez une clé sur console.curseforge.com.',
        'api_key' => [
            'label' => 'Clé d\'API CurseForge',
            'placeholder' => 'Laisser vide pour conserver la clé existante',
        ],
    ],

    'eligibility' => [
        'section' => 'Serveurs éligibles',
        'description' => 'Sélectionnez les eggs autorisés à installer un modpack. Tant qu\'aucun n\'est coché, l\'onglet Modpacks reste masqué sur tous les serveurs.',
        'eggs' => [
            'label' => 'Eggs autorisés',
            'helper' => 'Liste tous les eggs synchronisés depuis Pelican vers la base locale.',
            'empty' => 'Aucun egg encore synchronisé. Lancez d\'abord "Sync eggs" dans l\'admin.',
        ],
    ],

    'behavior' => [
        'section' => 'Comportement',
        'timeout' => [
            'label' => 'Délai d\'installation (minutes)',
            'helper' => 'Au-delà de cette durée sans progression, l\'installation est marquée en échec par le cron de reconciliation.',
        ],
        'provider' => [
            'label' => 'Fournisseur par défaut',
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
        'save' => 'Enregistrer',
        'import_egg' => [
            'label' => 'Importer l\'egg dans Pelican',
            'tooltip' => 'Pousse l\'egg installateur vers Pelican. Pelican identifie par UUID, le ré-import est idempotent.',
        ],
    ],

    'notifications' => [
        'saved' => 'Paramètres enregistrés',
        'egg_imported' => 'Egg importé dans Pelican (id: :id)',
        'egg_import_failed' => 'Échec de l\'import de l\'egg : :reason',
    ],
];
