<?php

return [
    'providers' => [
        'shop' => 'Shop',
        'google' => 'Google',
        'discord' => 'Discord',
        'linkedin' => 'LinkedIn',
        'paymenter' => 'Paymenter',
    ],
    'email_not_verified' => 'Cet e-mail n\'a pas été vérifié par le fournisseur.',
    'register_on_canonical_first' => 'Veuillez d\'abord vous inscrire sur :provider.',
    'cannot_unlink_last_method' => 'Impossible de délier votre seul moyen de connexion.',
    'provider_disabled' => 'Ce fournisseur de connexion n\'est pas activé.',
    'mail' => [
        'linked' => [
            'subject' => ':provider lié à votre compte',
            'body' => ':provider vient d\'être lié comme méthode de connexion sur votre compte.',
        ],
        'unlinked' => [
            'subject' => ':provider délié de votre compte',
            'body' => ':provider vient d\'être retiré comme méthode de connexion de votre compte.',
        ],
    ],
];
