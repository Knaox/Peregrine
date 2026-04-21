<?php

return [
    'login' => [
        'title' => 'Connexion',
        'button' => 'Se connecter',
        'oauth_button' => 'Se connecter avec :provider',
        'email' => 'Adresse e-mail',
        'password' => 'Mot de passe',
        'remember' => 'Se souvenir de moi',
        'forgot_password' => 'Mot de passe oublié ?',
    ],
    'register' => [
        'title' => 'Créer un compte',
        'button' => 'Créer un compte',
        'name' => 'Nom complet',
        'email' => 'Adresse e-mail',
        'password' => 'Mot de passe',
        'password_confirmation' => 'Confirmer le mot de passe',
    ],
    'logout' => 'Déconnexion',
    'failed' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
    'throttle' => 'Trop de tentatives de connexion. Veuillez réessayer dans :seconds secondes.',
    '2fa' => [
        'required_admin_setup' => 'La double authentification est requise pour les comptes administrateurs. Veuillez la configurer avant de continuer.',
        'invalid_code' => 'Ce code est invalide ou expiré.',
        'invalid_password' => 'Mot de passe incorrect.',
        'challenge_expired' => 'Votre vérification 2FA a expiré. Veuillez vous reconnecter.',
        'already_enabled' => 'La 2FA est déjà activée sur ce compte.',
        'not_enabled' => 'La 2FA n\'est pas activée sur ce compte.',
        'mail' => [
            'greeting' => 'Bonjour :name,',
            'meta' => [
                'timestamp' => 'Date : :time',
                'ip' => 'Adresse IP : :ip',
                'user_agent' => 'Agent utilisateur : :ua',
            ],
            'cta' => [
                'review_security' => 'Vérifier les paramètres de sécurité',
            ],
            'footer' => [
                'not_me' => 'Si ce n\'était pas vous, contactez immédiatement votre administrateur.',
            ],
            'enabled' => [
                'subject' => '2FA activée sur votre compte',
                'body' => 'La double authentification vient d\'être activée sur votre compte.',
            ],
            'disabled' => [
                'subject' => '2FA désactivée sur votre compte',
                'body' => 'La double authentification vient d\'être désactivée sur votre compte.',
            ],
            'recovery_regenerated' => [
                'subject' => 'Vos codes de récupération ont été régénérés',
                'body' => 'Un nouvel ensemble de codes de récupération 2FA a été généré pour votre compte. Les codes précédents ne fonctionnent plus.',
            ],
        ],
    ],
];
