<?php

declare(strict_types=1);

return [
    'page' => [
        'navigation' => 'Stripe',
        'title' => 'Intégration Stripe',
        'subtitle' => 'Secret webhook Stripe, clé API et URLs côté client. Le multi-shop, les webhooks Pelican et le provisioning sont sur des pages dédiées.',
    ],
    'docs_link' => 'Documentation',
    'sections' => [
        'info' => [
            'title' => 'Carte des intégrations',
            'description' => 'Où chaque partie du système se configure.',
        ],
        'inbound' => [
            'title' => 'Stripe entrant',
            'description' => 'Requis pour que Peregrine reçoive les événements Stripe (checkout terminé, subscription mise à jour, remboursements, disputes…). Récupère les valeurs depuis ton Dashboard Stripe.',
        ],
        'customer' => [
            'title' => 'URLs côté client',
            'description' => 'Utilisées dans les emails de lifecycle envoyés par Peregrine (serveur suspendu, fin d\'essai…) pour que le client gère son abonnement.',
        ],
    ],
    'info' => [
        'multi_shop' => [
            'label' => 'Multi-shop',
            'body' => 'Les shops tiers (avec leurs clés API et webhooks sortants) se gèrent sur la page admin Shops :',
        ],
        'pelican' => [
            'label' => 'Webhooks Pelican',
            'body' => 'Les webhooks Pelican sont toujours actifs et se configurent indépendamment :',
        ],
        'third_party' => [
            'label' => 'Facturation tierce (WHMCS, Paymenter, …)',
            'body' => 'Si ton système de facturation crée les serveurs directement via l\'API Pelican, fais pointer ses webhooks sortants vers /api/pelican/webhook pour que Peregrine mirror l\'état Pelican. Prérequis : les webhooks Pelican doivent être configurés (secret de signature + URL côté Pelican) — sans ça, /api/pelican/webhook rejette tous les appels :',
        ],
    ],
    'fields' => [
        'webhook_secret' => 'Secret de signature des webhooks Stripe',
        'api_secret' => 'Secret API Stripe',
        'billing_portal_url' => 'URL de fallback du Stripe Billing Portal',
        'resubscribe_url' => 'Template d\'URL de resubscribe',
        'grace_period_days' => 'Période de grâce (jours)',
        'shared_secret' => 'Secret partagé de resubscribe (HMAC)',
        'shared_secret_action_tooltip' => 'Générer un nouveau secret aléatoire',
    ],
    'helpers' => [
        'webhook_secret' => 'Utilisé pour vérifier la signature des événements Stripe entrants. Sans ça, /api/stripe/webhook rejette tous les appels. Laisser vide pour conserver la valeur existante ; saisir une nouvelle valeur pour la roter.',
        'api_secret' => 'Utilisé par Peregrine pour appeler Stripe (ex: récupérer l\'URL d\'une invoice pour les emails reçus, créer une session Customer Portal). Optionnel — sans ça, les emails se rabattent sur l\'URL Billing Portal ci-dessous.',
        'billing_portal_url' => 'URL statique de fallback vers ton Stripe Customer Portal. Utilisée dans les emails de lifecycle quand la création de session API échoue ou qu\'aucun secret API n\'est configuré.',
        'resubscribe_url' => 'Template appliqué dans l\'email « votre serveur est suspendu ». La signature porte sur {server_id}|{configuration_id}|{subscription_id}|{ts}, signée avec bridge_shop_shared_secret. Placeholders : {server_id}, {configuration_id}, {subscription_id}, {ts}, {signature}. Le {subscription_id} (ancien abonnement Stripe) permet au shop de réutiliser le prix d\'origine et de vérifier que l\'abonnement appartient bien à l\'utilisateur. Le placeholder {configuration} (internal_name) reste interpolé pour les shops legacy mais ne participe plus à la signature.',
        'grace_period_days' => 'Jours conservés entre l\'annulation d\'une subscription et la suppression effective du serveur. Le client peut se resubscribe pendant cette fenêtre. Défaut : 14 jours.',
        'shared_secret' => 'Clé HMAC qui signe les liens de resubscribe. Copie EXACTEMENT cette valeur dans le réglage « Bridge Shared Secret » du plugin WordPress (réglages Peregrine → Stripe). Les deux côtés doivent être identiques, sinon les liens sont rejetés. Stockée chiffrée. Régénérer invalide tous les liens déjà envoyés.',
    ],
    'placeholders' => [
        'webhook_secret' => 'whsec_…',
        'api_secret' => 'sk_live_…',
    ],
    'badges' => [
        'webhook_configured' => 'Webhook Stripe configuré',
        'webhook_missing' => 'Webhook Stripe manquant',
        'shop_configured' => 'Shop(s) actif(s)',
        'shop_missing' => 'Aucun shop actif',
    ],
    'notifications' => [
        'saved' => 'Réglages Stripe enregistrés.',
        'secret_generated_title' => 'Nouveau secret généré',
        'secret_generated_body' => 'Enregistre pour l\'appliquer, puis copie-le dans le plugin WordPress. Les anciens liens de resubscribe seront invalidés.',
    ],
];
