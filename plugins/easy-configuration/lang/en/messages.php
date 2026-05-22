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
];
