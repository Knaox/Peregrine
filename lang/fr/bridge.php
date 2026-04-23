<?php

return [
    'webhook' => [
        'received' => 'Webhook reçu avec succès.',
        'invalid_signature' => 'Signature de webhook invalide.',
        'processing' => 'Le webhook est en cours de traitement.',
    ],
    'provisioning' => [
        'started' => 'Provisionnement du serveur lancé.',
        'completed' => 'Provisionnement du serveur terminé.',
        'failed' => 'Le provisionnement du serveur a échoué.',
    ],
    'subscription' => [
        'updated' => 'Abonnement mis à jour.',
        'cancelled' => 'Abonnement annulé.',
        'expired' => 'Abonnement expiré. Serveur suspendu.',
    ],
    'errors' => [
        'disabled' => 'L\'API Bridge est désactivée.',
        'secret_not_configured' => 'Le secret partagé Bridge n\'a pas été configuré.',
        'invalid_signature' => 'Signature HMAC invalide.',
        'invalid_timestamp' => 'En-tête X-Bridge-Timestamp manquant ou mal formé.',
        'timestamp_expired' => 'Horodatage de la requête en dehors de la fenêtre de 5 minutes.',
        'plan_not_found' => 'Aucun plan trouvé avec ce shop_plan_id.',
    ],
    'plan' => [
        'status' => [
            'ready' => 'Prêt',
            'needs_config' => 'Configuration requise',
            'inactive' => 'Inactif',
            'sync_error' => 'Erreur de synchronisation',
        ],
    ],
    'settings' => [
        'enabled_label' => 'Activer l\'API Bridge',
        'shop_url_label' => 'URL de base du Shop',
        'shared_secret_label' => 'Secret HMAC partagé',
        'generate_secret' => 'Générer un nouveau secret',
        'saved' => 'Paramètres Bridge enregistrés',
    ],
];
