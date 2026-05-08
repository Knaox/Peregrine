<?php

return [
    'resource' => [
        'label' => 'Egg',
        'plural' => 'Eggs',
        'navigation' => 'Eggs',
    ],
    'sections' => [
        'metadata' => 'Egg metadata',
        'metadata_description' => 'Managed by Pelican — edit in Pelican to change.',
        'local' => 'Local presentation',
        'local_description' => 'Override the banner image used in the player UI. Stored locally — Pelican never reads this.',
    ],
    'helpers' => [
        'banner' => 'Recommended: 800x450px (16:9). Max 2MB.',
    ],
    'delete' => [
        'row' => 'Deletes this egg + every server built from it (FK cascade). Pick "Peregrine only" to drop just the local copy.',
        'bulk' => 'Each deleted egg cascades to every server using it. Double-check before confirming.',
    ],
    'sync' => [
        'label' => 'Sync eggs',
        'success' => 'Synced :count eggs from Pelican',
    ],
    'back_to_list' => 'Back to list',
];
