<?php

declare(strict_types=1);

return [
    'resource' => [
        'label' => 'Template de ressources',
        'plural' => 'Templates de ressources',
        'navigation' => 'Templates',
    ],
    'sections' => [
        'identity' => 'Identité',
        'resources' => 'Ressources Pelican',
    ],
    'fields' => [
        'name' => 'Nom',
        'ram' => 'RAM',
        'cpu' => 'CPU',
        'disk' => 'Disque',
        'swap' => 'Swap',
        'io_weight' => 'Poids I/O',
        'cpu_pinning' => 'CPU pinning',
        'usage' => 'Utilisations',
    ],
    'helpers' => [
        'name' => 'Nom marketing court (ex. « Medium-Medium », « Performance-Large »). Doit être unique. Apparaît dans le sélecteur des configurations.',
        'io_weight' => 'Pondération I/O Pelican (10–1000). 500 = standard.',
        'cpu_pinning' => 'Cores CPU à pinner. Format Pelican (ex. « 0-3 »). Vide = aucun pinning.',
    ],
    'duplicate' => [
        'label' => 'Dupliquer',
        'modal_heading' => 'Dupliquer le template « :name » ?',
        'modal_description' => 'Crée une copie de ce template avec un nouveau `name` (suffixé `-copy`). Toutes les specs sont reprises à l\'identique. Les configurations rattachées au template source ne sont PAS re-rattachées à la copie.',
        'submit' => 'Oui, dupliquer',
        'notification_title' => 'Template dupliqué',
        'notification_body' => 'La copie « :name » a été créée. Vous êtes redirigé sur sa page d\'édition.',
    ],
    'duplicate_bulk' => [
        'label' => 'Dupliquer la sélection',
        'modal_heading' => 'Dupliquer les templates sélectionnés ?',
        'modal_description' => ':count template(s) seront clonés. Chaque copie reçoit un nouveau `name` suffixé (`-copy`, `-copy-2`, …).',
        'submit' => 'Oui, dupliquer la sélection',
        'notification_title' => 'Templates dupliqués',
        'notification_body' => ':count copie(s) créée(s) avec succès.',
    ],
    'delete' => [
        'modal_heading' => 'Supprimer le template « :name » ?',
        'no_configs' => 'Aucune configuration ne référence actuellement ce template.',
        'with_configs' => ':count configuration(s) référencent actuellement ce template. Leur `resource_template_id` sera mis à NULL — elles passeront en statut « needs_config » jusqu\'à ce qu\'un admin les rattache à un autre template.',
        'irreversible' => 'Cette action est irréversible.',
        'submit' => 'Oui, supprimer définitivement',
    ],
];
