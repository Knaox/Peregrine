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
    'pelican' => [
        'events' => [
            'server_created' => 'Serveur créé dans Pelican',
            'server_updated' => 'Serveur mis à jour dans Pelican',
            'server_deleted' => 'Serveur supprimé dans Pelican',
            'server_installed' => 'Installation du serveur terminée',
            'user_created' => 'Utilisateur créé dans Pelican',
            'user_updated' => 'Profil utilisateur mis à jour dans Pelican',
            'user_deleted' => 'Utilisateur supprimé de Pelican',
            'node_created' => 'Node ajouté dans Pelican',
            'node_updated' => 'Node mis à jour dans Pelican',
            'node_deleted' => 'Node supprimé de Pelican',
            'egg_created' => 'Egg ajouté dans Pelican',
            'egg_updated' => 'Egg mis à jour dans Pelican',
            'egg_deleted' => 'Egg supprimé de Pelican',
            'egg_variable_created' => 'Variable d\'egg ajoutée',
            'egg_variable_updated' => 'Variable d\'egg mise à jour',
            'egg_variable_deleted' => 'Variable d\'egg supprimée',
            'ignored' => 'Évènement non supporté (enregistré pour audit)',
        ],
        'stuck_provisioning_badge' => 'Bloqué (webhook manquant)',
        'stuck_provisioning_tooltip' => 'Ce serveur attend depuis plus de 30 minutes le webhook de fin d\'installation Pelican. Très probablement les évènements :updated_server: et :server_installed: ne sont pas cochés dans votre Pelican /admin/webhooks. Consultez /admin/pelican-webhook-logs pour les évènements reçus et /docs/pelican-webhook pour le guide de configuration.',
    ],
];
