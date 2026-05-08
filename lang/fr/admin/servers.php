<?php

return [
    'resource' => [
        'label' => 'Serveur',
        'plural' => 'Serveurs',
        'navigation' => 'Serveurs',
    ],
    'tooltips' => [
        'stuck' => 'Ce serveur attend le webhook d\'install Pelican depuis plus de 30 minutes. Très probablement les events `event: Server\\Installed` et `updated: Server` ne sont pas cochés dans /admin/webhooks côté Pelican. Vérifiez /admin/pelican-webhook-logs pour les events entrants et /docs/pelican-webhook pour le guide de configuration.',
        'scheduled_deletion' => 'Ce serveur sera supprimé définitivement à la date affichée. Utilisez le menu d\'actions → Annuler la suppression planifiée pour le conserver.',
    ],
    'helpers' => [
        'pelican_id' => 'Identifiant interne Pelican. Le modifier re-mappe la ligne locale vers un autre serveur Pelican.',
        'idempotency' => 'Défini par ProvisionServerJob — garantit un seul serveur Pelican par checkout Stripe.',
        'stripe_subscription' => 'Lié à l\'abonnement actif du client. Vidé à l\'annulation.',
        'payment_intent' => 'Défini automatiquement depuis le checkout Stripe — lecture seule.',
        'scheduled_deletion' => 'Défini quand le client annule — le serveur est supprimé définitivement à cette date s\'il n\'est pas réactivé.',
        'egg' => 'L\'egg Pelican utilisé pour provisionner ce serveur. Détermine l\'image Docker et la commande de démarrage.',
        'plan' => 'Optionnel — relier ce serveur à un plan du Shop pour la réconciliation de facturation.',
    ],
    'retry' => [
        'label' => 'Relancer le provisioning',
        'modal_heading' => 'Relancer le provisioning de ":name" ?',
        'modal_description' => 'Re-dispatche un ProvisionServerJob avec la même clé d\'idempotence — la ligne locale est réutilisée, pas de duplicata. Le statut repasse en "provisioning". Assurez-vous que le worker queue tourne, sinon le job restera dans `jobs` indéfiniment.',
        'submit' => 'Relancer maintenant',
        'notification_title' => 'Relance dispatched',
        'notification_body' => 'ProvisionServerJob mis en queue pour ":name". Surveillez les logs queue pour voir l\'avancement.',
    ],
    'cancel_deletion' => [
        'label' => 'Annuler la suppression planifiée',
        'modal_heading' => 'Annuler la suppression planifiée de ":name" ?',
        'modal_description' => 'La suppression définitive est planifiée pour le :date. Annuler conservera le serveur en état suspendu. Pour rétablir l\'accès du client, pensez aussi à le désuspendre dans Pelican.',
        'submit' => 'Oui, conserver ce serveur',
        'notification_title' => 'Suppression planifiée annulée',
        'notification_body' => 'Le serveur ":name" ne sera pas supprimé. Il reste suspendu — désuspendez-le manuellement si le client retrouve son accès.',
    ],
    'sync' => [
        'label' => 'Synchroniser les serveurs',
        'modal_heading' => 'Synchroniser les serveurs depuis Pelican',
        'modal_description' => 'Récupère tous les serveurs depuis Pelican et importe les nouveaux dans Peregrine.',
        'no_new' => 'Aucun nouveau serveur trouvé',
        'imported' => ':count serveurs importés depuis Pelican',
    ],
    'back_to_list' => 'Retour à la liste',
];
