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
    ],

    'providers' => [
        'section' => 'Fournisseurs',
        'modrinth' => 'Modrinth',
        'curseforge' => 'CurseForge',
        'atlauncher' => 'ATLauncher',
        'ftb' => 'Feed The Beast',
        'technic' => 'Technic',
        'voidswrath' => 'VoidsWrath',
    ],

    'sort' => [
        'relevance' => 'Pertinence',
        'downloads' => 'Plus téléchargés',
        'updated' => 'Récemment mis à jour',
        'newest' => 'Les plus récents',
    ],

    'display' => [
        'section' => 'Affichage',
    ],

    'behavior' => [
        'section' => 'Comportement',
    ],

    'fields' => [
        'egg_ids' => [
            'label' => 'Eggs autorisés',
            'help' => 'Liste tous les eggs synchronisés depuis Pelican vers la base locale. Choisissez ceux dont les serveurs peuvent installer des modpacks.',
        ],
        'default_provider' => [
            'label' => 'Fournisseur par défaut',
        ],
        'default_sort' => [
            'label' => 'Tri par défaut',
            'help' => 'Ordre initial appliqué à la liste du marketplace.',
        ],
        'page_label' => [
            'label' => 'Libellé de l\'onglet',
            'help' => 'Optionnel. Par défaut, utilise la traduction « Modpacks ».',
        ],
        'page_route' => [
            'label' => 'Route de l\'onglet',
            'help' => 'Suffixe d\'URL ajouté après /servers/{id}. Minuscules, tirets, slash initial. Par défaut : /modpacks.',
        ],
        'modpacks_per_page' => [
            'label' => 'Modpacks par page',
            'help' => 'Nombre de cartes modpack affichées par page sur l\'onglet marketplace.',
        ],
        'install_timeout_minutes' => [
            'label' => 'Délai d\'installation (minutes)',
            'help' => 'Au-delà de cette durée sans progression, l\'installation est marquée en échec par le cron de réconciliation.',
        ],
        'cache_ttl_seconds' => [
            'label' => 'TTL cache fournisseurs (secondes)',
            'help' => 'Durée de mise en cache des réponses fournisseurs (recherches, métadonnées, versions). De 60 à 86400.',
        ],
    ],

    'java' => [
        'section' => 'Compatibilité Java',
        'description' => 'Contrôle comment l\'installeur choisit la version de Java et l\'image Docker pour chaque modpack. Tous les champs sont optionnels — laissez vide pour utiliser les valeurs par défaut livrées dans config/java-compatibility.php.',
        'default_java' => [
            'label' => 'Java par défaut',
            'placeholder' => 'Valeur par défaut du plugin (Java 17)',
            'help' => 'Utilisé quand aucune règle de compatibilité ne s\'applique (version MC inconnue, loader exotique). Remplace la valeur déclarée dans la config du plugin.',
        ],
        'images' => [
            'label' => 'Images Docker par version Java',
            'key_label' => 'Version Java',
            'value_label' => 'Image Docker',
            'add' => 'Ajouter une image',
            'help' => 'Surcharge clé par clé de la carte d\'images livrée. N\'ajoutez une entrée que pour les versions Java que vous voulez rediriger (ex : pointer java_21 sur un miroir privé) ; les autres restent sur les valeurs par défaut.',
        ],
        'rules' => [
            'label' => 'Règles de compatibilité',
            'help' => 'Les règles sont évaluées de haut en bas — la première qui correspond gagne. Les règles avec un loader spécifique DOIVENT être placées avant les règles génériques (sans loader). Si vous remplissez ce tableau, il REMPLACE intégralement la liste de règles livrée. Laissez vide pour garder les valeurs par défaut.',
            'add' => 'Ajouter une règle',
            'fields' => [
                'loader' => 'Loader',
                'loader_any' => 'N\'importe quel loader (y compris Vanilla)',
                'mc_min' => 'Version Minecraft min.',
                'mc_max' => 'Version Minecraft max.',
                'java' => 'Version Java',
            ],
        ],
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
