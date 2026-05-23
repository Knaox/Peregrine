<?php

declare(strict_types=1);

/**
 * Backend (Laravel) translations for the Easy Configuration plugin.
 * Accessed via `__('easy-configuration::messages.key')`. The player- and
 * admin-facing React UI is translated separately under frontend/i18n/.
 */
return [
    'name' => 'Easy Configuration',

    'validation' => [
        'invalid_template' => 'The template JSON is invalid: :error',
        'unknown_format' => 'Unsupported configuration file format: :format',
    ],

    'import' => [
        'unsupported_format' => "Couldn't detect the file format from its extension — pick one explicitly.",
        'server_unavailable' => 'This server is not ready yet (no Pelican identifier).',
        'read_failed' => "Couldn't read that file on the server. Check the path and that the server exists.",
        'list_failed' => "Couldn't list this folder. Check the server is online and reachable.",
    ],
];
