<?php

declare(strict_types=1);

/**
 * Traductions backend (Laravel) du plugin Easy Configuration.
 * Accès via `__('easy-configuration::messages.key')`. L'UI React joueur et
 * admin est traduite séparément sous frontend/i18n/.
 */
return [
    'name' => 'Configuration facile',

    'validation' => [
        'invalid_template' => 'Le JSON du template est invalide : :error',
        'unknown_format' => 'Format de fichier de configuration non supporté : :format',
    ],
];
