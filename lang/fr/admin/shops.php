<?php

declare(strict_types=1);

return [
    'resource' => [
        'label' => 'Shop',
        'plural' => 'Shops',
        'navigation' => 'Shops',
    ],
    'fields' => [
        'name' => 'Nom',
        'slug' => 'Slug',
        'domain' => 'Domaine',
        'status' => 'Statut',
        'metadata' => 'Métadonnées (JSON)',
        'api_keys_count' => 'Clés API',
        'configurations_count' => 'Configurations',
        'shop_external_id' => 'ID côté shop',
        'visible' => 'Visible',
        'sort_order' => 'Ordre',
    ],
    'helpers' => [
        'slug' => 'Identifiant stable URL-safe. Apparaît dans les logs d\'audit et les references Stripe metadata.',
        'domain' => 'Optionnel, informatif uniquement. Aucune authentification dérivée.',
        'metadata' => 'JSON libre. Persisté tel quel. Utile pour du tagging cross-système.',
    ],
    'status' => [
        'active' => 'Actif',
        'suspended' => 'Suspendu',
    ],
    'actions' => [
        'suspend' => 'Suspendre',
        'resume' => 'Réactiver',
        'status_updated' => 'Statut du shop mis à jour.',
        'docs' => 'Documentation',
    ],
];
