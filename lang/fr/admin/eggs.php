<?php

return [
    'resource' => [
        'label' => 'Egg',
        'plural' => 'Eggs',
        'navigation' => 'Eggs',
    ],
    'sections' => [
        'metadata' => 'Métadonnées Egg',
        'metadata_description' => 'Géré par Pelican — éditez dans Pelican pour modifier.',
        'local' => 'Présentation locale',
        'local_description' => 'Surcharger la bannière utilisée dans l\'UI joueur. Stockée localement — Pelican ne la lit jamais.',
    ],
    'helpers' => [
        'banner' => 'Recommandé : 800x450px (16:9). Max 2 Mo.',
    ],
    'delete' => [
        'row' => 'Supprime cet egg + tous les serveurs qui en sont issus (cascade FK). Choisissez "Peregrine uniquement" pour ne supprimer que la copie locale.',
        'bulk' => 'Chaque egg supprimé entraîne en cascade tous les serveurs qui l\'utilisent. Vérifiez deux fois avant de confirmer.',
    ],
    'sync' => [
        'label' => 'Synchroniser les eggs',
        'success' => ':count eggs synchronisés depuis Pelican',
    ],
    'back_to_list' => 'Retour à la liste',
];
