<?php

return [
    'page' => [
        'title' => 'Récepteur webhooks',
        'navigation' => 'Récepteur webhooks',
        'subtitle' => 'Configurez le token utilisé par Pelican pour pousser ses events.',
    ],
    'sections' => [
        'receiver' => 'Récepteur',
        'receiver_description' => 'Active ou désactive le endpoint webhook public. Désactivé, les appels Pelican retournent 503 et aucun event n\'est traité.',
        'token' => '1. Générer le token bearer',
        'token_description' => 'Pelican ne signe pas ses webhooks — l\'auth repose entièrement sur ce token.',
        'configure' => '2. Configurer Pelican (/admin/webhooks → Create Webhook)',
        'configure_description' => 'Champs du haut + headers + events à cocher. La liste des events est groupée par priorité — commencez par "Requis", ajoutez les autres au besoin.',
        'verify' => '3. Vérifier',
        'verify_description' => 'Une fois Pelican enregistré, chaque event arrive ici pour audit.',
    ],
    'fields' => [
        'enabled' => 'Activer le récepteur webhook Pelican',
        'enabled_helper' => 'Désactiver pour stopper temporairement la réception sans perdre le token configuré.',
        'token' => 'Token d\'authentification du webhook Pelican',
        'token_helper' => 'Cliquez sur l\'icône clé pour générer un token aléatoire de 64 caractères. Laisser vide à l\'enregistrement pour conserver la valeur stockée (toute rotation requiert de mettre à jour les headers Pelican en synchro).',
        'token_action_tooltip' => 'Générer un nouveau token aléatoire de 64 caractères',
        'top_fields' => 'Champs du haut',
        'headers' => 'Headers (gardez la ligne par défaut, ajoutez la seconde)',
        'events_required' => 'Requis (fin d\'installation + cycle de vie)',
        'events_required_note' => 'Ces cinq events sont obligatoires. `event: Server\\Installed` est le signal canonique de fin d\'installation (Pelican le déclenche dès que le script d\'installation termine) ; `updated: Server` est le signal secondaire (Pelican passe le statut de "installing" à null au même moment) et sert de filet de sécurité. Sans ces deux events, les serveurs restent en "provisioning" indéfiniment et un badge "bloqué" apparaît dans /admin/servers. `created: Server` / `deleted: Server` / `created: User` sont les events standards du cycle de vie.',
        'events_recommended' => 'Recommandé (réduit la sync manuelle)',
        'events_recommended_note' => 'Mirror les changements d\'email/nom utilisateur, l\'infrastructure des nodes, et les définitions egg/variable en temps réel. Avec ces events cochés, les commandes manuelles `sync:users / sync:nodes / sync:eggs` deviennent des filets de sécurité rarement nécessaires.',
        'events_blocklist' => 'À NE PAS cocher',
        'events_blocklist_note' => 'Allocation / Backup / Database / DatabaseHost / ServerTransfer / Subuser : Peregrine n\'a plus de table pour ces ressources — la SPA les lit en direct sur Pelican quand l\'utilisateur ouvre /network, /databases, /backups, /sous-utilisateurs. Les cocher n\'alimente rien côté nous et le récepteur les enregistre comme ignorés. `Schedule` et `Task` se déclenchent à chaque tick cron (flood). `ActivityLog` se déclenche à chaque action user (flood). `ApiKey` met à jour `last_used_at` à chaque appel API (bruit). `Webhook` / `WebhookConfiguration` créent des boucles infinies.',
        'docs' => 'Guide pas-à-pas',
        'docs_note' => 'Guide complet de configuration, dépannage, limites connues, et fonctionnement de la sync de statut d\'installation selon les modes Bridge.',
        'audit' => 'Audit en direct des webhooks reçus',
        'audit_note' => 'Tous les events webhook acceptés avec statut HTTP, message d\'erreur et hash d\'idempotence.',
    ],
    'header_descriptions' => [
        'pelican_default' => 'Valeur par défaut Pelican — gardez-la',
        'add_row' => 'Ajoutez cette ligne',
    ],
    'header_values' => [
        'token_placeholder' => 'Bearer <token ci-dessus>',
    ],
    'top_fields' => [
        'type' => 'Regular',
        'description' => 'Peregrine — récepteur webhook Pelican',
    ],
    'notifications' => [
        'token_generated_title' => 'Token généré',
        'token_generated_body' => 'Copiez-le depuis le champ, collez-le dans les headers webhook côté Pelican, puis cliquez Enregistrer.',
        'saved' => 'Paramètres webhook Pelican enregistrés',
    ],
];
