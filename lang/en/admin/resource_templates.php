<?php

declare(strict_types=1);

return [
    'resource' => [
        'label' => 'Resource template',
        'plural' => 'Resource templates',
        'navigation' => 'Templates',
    ],
    'sections' => [
        'identity' => 'Identity',
        'resources' => 'Pelican resources',
    ],
    'fields' => [
        'name' => 'Name',
        'ram' => 'RAM',
        'cpu' => 'CPU',
        'disk' => 'Disk',
        'swap' => 'Swap',
        'io_weight' => 'I/O weight',
        'cpu_pinning' => 'CPU pinning',
        'usage' => 'Used by',
    ],
    'helpers' => [
        'name' => 'Short marketing-friendly name (e.g. "Medium-Medium", "Performance-Large"). Must be unique. Surfaces in the configuration picker.',
        'io_weight' => 'Pelican I/O weight (10–1000). 500 = standard.',
        'cpu_pinning' => 'CPU cores to pin. Pelican format (e.g. "0-3"). Empty = no pinning.',
    ],
    'duplicate' => [
        'label' => 'Duplicate',
        'modal_heading' => 'Duplicate template ":name"?',
        'modal_description' => 'Creates a copy of this template with a fresh `name` (suffixed `-copy`). All specs are copied verbatim. Configurations bound to the source are NOT re-bound to the copy.',
        'submit' => 'Yes, duplicate',
        'notification_title' => 'Template duplicated',
        'notification_body' => 'Copy ":name" was created. Redirecting to its edit page.',
    ],
    'duplicate_bulk' => [
        'label' => 'Duplicate selection',
        'modal_heading' => 'Duplicate selected templates?',
        'modal_description' => ':count template(s) will be cloned. Each copy receives a fresh suffixed `name` (`-copy`, `-copy-2`, …).',
        'submit' => 'Yes, duplicate selection',
        'notification_title' => 'Templates duplicated',
        'notification_body' => ':count copy/copies created successfully.',
    ],
    'delete' => [
        'modal_heading' => 'Delete template ":name"?',
        'no_configs' => 'No configuration currently references this template.',
        'with_configs' => ':count configuration(s) currently reference this template. Their `resource_template_id` will be set to NULL — they\'ll show as "needs_config" until an admin re-binds them to another template.',
        'irreversible' => 'This is irreversible.',
        'submit' => 'Yes, delete permanently',
    ],
];
