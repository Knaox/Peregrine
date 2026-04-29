# Récepteur Webhook Pelican — Guide de configuration

Cette page est la documentation à destination des opérateurs pour l'endpoint
`/api/pelican/webhook`. Le récepteur est **découplé du mode Bridge** — il
fonctionne dans n'importe quel mode (Shop+Stripe, Paymenter, ou même Bridge
désactivé), tant que l'on active le toggle et que l'on configure le bearer
token.

> Chemin de l'UI admin : `/admin/pelican-webhook-settings`

## Ce qu'il fait

Le récepteur écoute le **système de webhooks sortants de Pelican**
(`/admin/webhooks` sur le panel Pelican) et miroir un sous-ensemble choisi de
changements Server / User / Node / Egg dans la base de données locale de
Peregrine. Deux cas d'usage principaux :

| Mode Bridge    | Ce que le webhook vous apporte                                            |
|----------------|---------------------------------------------------------------------------|
| `shop_stripe`  | Fin d'installation → le serveur passe de `provisioning` à `active` et l'email "votre serveur est jouable" est envoyé. **Requis** depuis que le fallback par polling a été supprimé. |
| `paymenter`    | Miroir complet de l'état Pelican — Paymenter crée/suspend/supprime les serveurs dans Pelican, Pelican forwarde chaque changement ici. |
| `disabled`     | Identique à Paymenter. Utile pour les serveurs importés par l'admin.      |

Dans **tous les modes**, le **Shop reste toujours la source de vérité pour la
propriété et la facturation**. Pelican n'est autorisé à écrire que les
champs que le Shop ne possède pas :

- `pelican_server_id` (déjà défini lors du provisioning)
- `identifier` (UUID court Pelican)
- `egg_id` (miroir)
- `paymenter_service_id` (mode Paymenter uniquement)
- La transition d'installation `provisioning` → `active` / `provisioning_failed`

Pelican n'écrasera **jamais** `user_id`, `name`, `plan_id`,
`stripe_subscription_id`, ni le statut de facturation (`suspended` /
`terminated`) sur un serveur appartenant au Shop.

## Critique : configuration obligatoire pour Shop+Stripe

> ⚠️ Depuis la Phase 1, le polling `MonitorServerInstallationJob` a été
> **supprimé**. Le webhook Pelican est désormais le **seul** signal qui fait
> passer un serveur fraîchement provisionné de `provisioning` à `active` et
> qui déclenche l'email "votre serveur est jouable".
>
> Si vous opérez en mode `shop_stripe` et que vous ne configurez pas le
> webhook avec au moins `updated: Server` coché, les serveurs de vos clients
> resteront bloqués en `provisioning` indéfiniment. La page `/admin/servers`
> remonte les serveurs bloqués (>30 min en `provisioning`) avec un badge
> rouge — mais le bon correctif est toujours de configurer le webhook.

## 1. Activer le récepteur

`/admin/pelican-webhook-settings` → toggle "Enable Pelican webhook receiver" → cliquer 🔑 pour générer un token de 64 caractères → Sauvegarder.

Le token est chiffré au repos. Sauvegarder un champ vide conserve la valeur existante.

## 2. Configurer Pelican (`/admin/webhooks` → Create Webhook)

| Champ          | Valeur                                                         |
|----------------|----------------------------------------------------------------|
| Type           | Regular                                                        |
| Description    | `Peregrine — Pelican webhook receiver`                         |
| Endpoint       | `https://<your-peregrine-host>/api/pelican/webhook`            |

### Headers

Conserver la ligne par défaut de Pelican `X-Webhook-Event: {{event}}`, puis ajouter :

```
Authorization: Bearer <the token from /admin/pelican-webhook-settings>
```

### Comment cocher les events

1. Allez dans `/admin/webhooks` sur votre panel Pelican
2. Cliquez sur votre webhook Peregrine (ou "Create Webhook" s'il n'existe pas encore)
3. Ouvrez l'onglet "Events"
4. Utilisez la barre de recherche pour trouver les événements un par un
5. Cochez chaque événement des listes ci-dessous
6. Sauvegardez

## 3. Liste complète des events supportés (par priorité)

### Requis — fin d'installation + cycle de vie

Les cinq sont obligatoires. `event: Server\Installed` est le signal canonique
de fin d'installation — sans lui, les serveurs fraîchement provisionnés ne
passeront jamais à `active`.

| Événement                | Effet en local                                                                            | Si non coché                                                |
|--------------------------|-------------------------------------------------------------------------------------------|------------------------------------------------------------|
| `created: Server`        | Miroir un nouveau serveur Pelican vers la DB locale (ou remplit `identifier` / `egg_id` pour les lignes appartenant au Shop). | Serveurs visibles dans Pelican mais pas dans `/admin/servers`. |
| `updated: Server`        | Détecte la fin d'installation (`installing` → `null`), bascule `provisioning` → `active`. **Couvre aussi** suspend/unsuspend/rename/build update. Sert de filet de sécurité au signal `Server\Installed`. | Drift de statut (rename / suspend / unsuspend non miroirés). |
| `deleted: Server`        | Supprime la ligne locale quand Pelican supprime un serveur (mode orchestrateur uniquement — les lignes appartenant au Shop sont conservées pour revue admin). | Les serveurs supprimés côté Pelican subsistent dans `/admin/servers`. |
| `created: User`          | Miroir un nouvel utilisateur Pelican dans `users` (ignoré en mode `shop_stripe` — le Shop possède la création utilisateur). | Nouveaux utilisateurs créés par l'orchestrateur non visibles dans `/admin/users`. |
| `event: Server\Installed`| Signal canonique de fin d'installation — déclenche l'email « serveur jouable » et bascule `provisioning` → `active` instantanément. | Serveurs payés via Stripe bloqués en `provisioning` jusqu'à ce que `updated: Server` se déclenche (badge « stuck » dans `/admin/servers` après 30 min). |

### Recommended — Phase 1 (réduit la sync manuelle)

Remplace les commandes admin manuelles `sync:users / sync:nodes / sync:eggs`.

| Événement                | Effet en local                                                       | Si non coché                                                       |
|--------------------------|----------------------------------------------------------------------|-------------------------------------------------------------------|
| `updated: User`          | Miroir le changement d'email/nom effectué dans le panel Pelican (tous modes Bridge). | `php artisan sync:users` manuel pour récupérer les changements.   |
| `deleted: User`          | Détache `pelican_user_id` (ne hard-delete jamais — l'utilisateur local conserve son abonnement Stripe + OAuth). | Drift : l'utilisateur local conserve un `pelican_user_id` obsolète. |
| `created: Node`          | Ajoute un nouveau node Pelican dans `/admin/nodes`.                  | Lancer `sync:nodes` après chaque ajout de node.                   |
| `updated: Node`          | Miroir les changements fqdn / name / memory / disk.                  | La ligne node locale dérive de Pelican.                           |
| `deleted: Node`          | Supprime le node (refusé si un plan le référence encore via `default_node_id` / `allowed_node_ids`). | Ligne node obsolète.                                              |
| `created: Egg`           | Ajoute un nouvel egg (Minecraft, ARK, Rust…) dans `/admin/eggs`.     | Lancer `sync:eggs` après chaque import d'egg.                     |
| `updated: Egg`           | Miroir les changements docker_image / startup / description / tags. | L'egg local dérive de Pelican.                                    |
| `deleted: Egg`           | Supprime l'egg (refusé si un serveur ou un plan l'utilise encore).   | Ligne egg obsolète.                                               |
| `created: EggVariable`   | Resync l'egg parent avec sa liste complète de variables.             | Le provisioning de plan peut casser si une nouvelle variable est requise. |
| `updated: EggVariable`   | Identique ci-dessus.                                                 | Les valeurs par défaut dérivent.                                  |
| `deleted: EggVariable`   | Identique ci-dessus.                                                 | L'egg local conserve des variables obsolètes.                     |

### Phase 2 preview — miroirs DB-local (pas encore actifs)

Réservés à la prochaine release Phase 2. Cochez-les dès maintenant si vous
le souhaitez — le récepteur les enregistrera comme `ignored` jusqu'à ce que
la Phase 2 soit livrée, sans impact.

| Événement                                 | Effet futur (Phase 2)                                                    |
|-------------------------------------------|--------------------------------------------------------------------------|
| `created/updated/deleted: Backup`         | `/server/{id}/backups` lit depuis la DB locale au lieu d'appeler Pelican à chaque fois. |
| `created/updated/deleted: Allocation`     | `/server/{id}/network` lit depuis la DB locale.                          |
| `created/updated/deleted: Database`       | `/server/{id}/databases` lit depuis la DB locale (mot de passe reste API live). |
| `created/updated/deleted: DatabaseHost`   | Miroir des métadonnées du host de base de données.                       |
| `created/updated/deleted: ServerTransfer` | Badge "transfert en cours" sur la fiche serveur.                         |

### À NE PAS cocher

| Événement                              | Pourquoi                                                                         |
|----------------------------------------|----------------------------------------------------------------------------------|
| `event: ActivityLogged`                | Se déclenche à chaque action utilisateur (power.start, command, file.download, etc.). Volume trop élevé — saturerait le rate limiter. |
| `created/updated/deleted: ActivityLog` | Identique ci-dessus.                                                             |
| `created/updated: Schedule`            | Se déclenche à chaque tick cron (chaque exécution de schedule = 2 webhooks). Flood garanti. |
| `created/updated: Task`                | Même schéma de flood que Schedule.                                               |
| `created/updated: ApiKey`              | `last_used_at` mis à jour à chaque appel API Pelican → bruit constant.           |
| `created/updated: Webhook`             | La table de log des webhooks elle-même — créerait des boucles infinies si non blacklistée. |
| `created/updated: WebhookConfiguration`| Configuration webhook propre à Pelican — méta-données, sans intérêt à mirrorer. |
| `created/updated: Role` / `NodeRole`   | RBAC Pelican — Peregrine a son propre modèle admin, aucun intérêt à mirrorer.    |

## 4. Tableau récap par effet

| Si vous voulez…                                       | Cochez ces événements                                                          |
|-------------------------------------------------------|--------------------------------------------------------------------------------|
| Que les paiements Stripe provisionnent correctement les serveurs | `created: Server`, `updated: Server`, `deleted: Server`, `created: User`       |
| Les changements d'email / nom temps réel depuis Pelican | `updated: User`, `deleted: User`                                               |
| L'auto-découverte des nouveaux nodes ajoutés dans Pelican | `created: Node`, `updated: Node`, `deleted: Node`                              |
| L'auto-découverte des nouveaux eggs (types de jeu)    | `created/updated/deleted: Egg` + `created/updated/deleted: EggVariable`        |
| L'aperçu Phase 2 (miroirs Backup/Allocation/Database) | La liste Phase 2 ci-dessus (sans impact pour l'instant, s'activera à la livraison) |

## 5. Vérifier

`/admin/pelican-webhook-logs` montre chaque événement accepté avec son
statut HTTP, son message d'erreur, et son hash d'idempotence. Cliquez sur
"Save" sur la page webhook Pelican — vous devriez voir une ligne heartbeat
apparaître en quelques secondes.

## Comment fonctionne la fin d'installation en mode shop_stripe

```
1. Customer pays → Stripe webhook → ProvisionServerJob
2. Peregrine creates the local Server row (status = provisioning)
3. Peregrine calls Pelican createServer (server is now installing)
4. Local Server row STAYS in `provisioning` — no preemptive flip to `active`
5. Pelican finishes the install script
6. Pelican fires `updated: Server` with status flipping from "installing" → null
7. Peregrine's webhook receiver :
   - Updates Server.status from `provisioning` to `active`
   - Fires `ServerInstalled` event
   - SendServerInstalledNotification mails the customer
```

Si le webhook n'arrive jamais (admin a oublié de cocher `updated: Server`,
problème réseau, etc.), le serveur reste en `provisioning`. La page
`/admin/servers` affiche un badge rouge "stuck" après 30 min avec une
infobulle pointant vers cette page. **Il n'y a aucun fallback de polling
automatique** — l'échec explicite est intentionnel, pour que les admins
remarquent immédiatement une mauvaise configuration plutôt qu'un sauvetage
silencieux qui masquerait le vrai problème.

## Idempotence

Pelican **ne retry pas** en cas d'échec et **ne fournit pas** d'event id.
Peregrine dérive un hash :

```
sha256( event_type | model_id | updated_at | sha256(body) )
```

Chaque événement accepté est enregistré dans `pelican_processed_events`. Les
événements identiques ré-émis sont dédupliqués et le second hit retourne
`{"received": true, "idempotent": true}`. Les lignes plus vieilles que 2
jours sont purgées quotidiennement par la commande
`pelican:clean-processed-events` (Pelican ne retry jamais, donc une fenêtre
de rétention courte suffit).

## Dépannage

- **503 `pelican.webhook_disabled`** : le toggle est désactivé dans
  `/admin/pelican-webhook-settings`.
- **503 `pelican.token_not_configured`** : activer le toggle, générer un
  token, sauvegarder.
- **401 `pelican.invalid_token`** : le token dans le header `Authorization`
  de Pelican ne correspond pas à celui stocké dans Peregrine. Cliquer 🔑
  pour régénérer dans Peregrine, puis mettre à jour les headers webhook
  Pelican en parallèle.
- **429** : la limite de throttle a été atteinte. Pelican a déclenché plus
  d'événements que le rate limiter `pelican-webhook` n'en autorise dans la
  fenêtre temporelle. Investiguer côté Pelican — généralement un événement
  flood-prone coché par erreur (Schedule, ActivityLog, ApiKey).
- **Serveur bloqué en `provisioning`** : vérifiez
  `/admin/pelican-webhook-logs` pour l'événement `event: Server\Installed`
  correspondant. S'il manque, le webhook n'a pas atteint Peregrine (très
  probablement l'event n'est pas coché dans Pelican). Cochez **les deux**
  `event: Server\Installed` (signal principal) et `updated: Server` (filet
  de sécurité) dans l'onglet events du webhook Pelican — la prochaine
  installation basculera correctement.
- **`/admin/users` ne reflète pas un changement d'email récent** : cocher
  `updated: User` dans Pelican. En attendant, lancer `php artisan
  sync:users` pour rattraper manuellement.
- **`/admin/nodes` manque un node que vous avez ajouté** : cocher
  `created: Node` dans Pelican. En attendant, lancer `php artisan
  sync:nodes`.

## Phase 2 — Mirror local étendu

À partir de la Phase 2, les pages utilisateur **Backups**, **Databases** et
**Network** lisent leurs données depuis des tables miroirs locales
(`pelican_backups`, `pelican_databases`, `pelican_allocations`,
`pelican_database_hosts`, `pelican_server_transfers`) au lieu d'appeler
Pelican à chaque chargement de page. Résultat : pages instantanées
(5-10 ms au lieu de 200 ms cache miss), Peregrine reste fonctionnel
même si Pelican est temporairement indisponible.

### Comment activer Phase 2

1. Cocher dans Pelican `/admin/webhooks` les events Phase 2 (cf. liste
   "Recommended" et "Phase 2 preview" plus haut).
2. Lancer `php artisan pelican:backfill-mirrors` — la commande importe
   tous les backups, databases, allocations existants. Idempotente,
   resumable (`--resume` après interruption), chunked (500 par batch).
3. Vérifier l'absence de drift : `php artisan pelican:diff-mirror all`.
4. Activer le toggle **"Phase 2 — Lectures DB locale"** dans
   `/admin/pelican-webhook-settings`.

À ce moment-là les controllers basculent en lecture DB locale. La
réconciliation horaire automatique rattrape les rares cas de drift
(mass-updates Pelican qui bypassent les events).

### Réconciliation à 2 cadences

Un cron hourly choisit automatiquement son scope selon le toggle
webhook :

| Webhook actif | Réconciliation |
|---|---|
| `pelican_webhook_enabled = true`  | Hourly safety-net : Backup + Allocation uniquement (rattrape `PruneOrphanedBackupsCommand`, transfers, etc.) |
| `pelican_webhook_enabled = false` | Hourly fullSync : toutes les ressources miroirées (Servers, Users, Nodes, Eggs, Backups, Databases, Allocations, Transfers) — mode "polling complet" pour qui ne veut pas configurer le webhook |

Logs visibles dans `/admin/bridge-sync-logs` (action `pelican_mirror_reconcile`).

### Sécurité

- **Aucun secret n'est miroré localement** — les fields `password` (DB),
  `daemon_token` (Wings), `mfa_app_secret` (User) sont `$hidden +
  encrypted` côté Pelican, donc absents du payload webhook.
- Les jobs miroirs whitelist explicite des champs persistés. Un nouveau
  field Pelican = ignoré silencieusement par défaut.
- Canary explicite : si Pelican émet jamais un `password` dans un
  payload (régression future), un `Log::critical` se déclenche.

### Auditer un drift

```bash
# Résumé global (tous les types)
php artisan pelican:diff-mirror all

# Cible un type particulier
php artisan pelican:diff-mirror backups
php artisan pelican:diff-mirror users
```

La sortie liste : nombre côté remote (Pelican), nombre local, manquants,
orphelins. Lecture seule — n'écrit jamais, ne corrige rien. Pour
forcer une resync : `php artisan pelican:backfill-mirrors --fresh`.

### Dépannage Phase 2

- **Page Backups vide après activation du toggle** : tu n'as pas lancé
  `pelican:backfill-mirrors` avant. Désactive le toggle, lance le
  backfill, réactive.
- **`/admin/pelican-mirror/*` cachés du menu** : ces pages ne s'affichent
  que si le webhook receiver est activé (`pelican_webhook_enabled=true`).
- **Drift après transfer Pelican** : la réconciliation horaire le
  rattrape automatiquement. Pour forcer immédiatement : déclencher la
  commande artisan `pelican:backfill-mirrors --only=allocations`.

## Cartographie admin Filament

| Page                                       | Quand visible                                  |
|--------------------------------------------|------------------------------------------------|
| `/admin/pelican-webhook-settings`          | Toujours                                       |
| `/admin/pelican-webhook-logs`              | Quand `pelican_webhook_enabled` vaut `true`    |
| `/admin/bridge-settings`                   | Toujours (Bridge a ses propres settings)       |
| `/admin/bridge-sync-logs`                  | Uniquement en mode `shop_stripe`               |
| `/admin/servers` (avec badge stuck)        | Toujours — le badge apparaît sur les lignes `provisioning` plus vieilles que 30 min |
| `/admin/pelican-backups` (Phase 2)         | Quand `pelican_webhook_enabled` vaut `true`    |
| `/admin/pelican-allocations` (Phase 2)     | Quand `pelican_webhook_enabled` vaut `true`    |
| `/admin/pelican-server-transfers` (Phase 2)| Quand `pelican_webhook_enabled` vaut `true`    |

## Référence des clés de settings

| Clé                              | Type    | Notes                                                            |
|----------------------------------|---------|------------------------------------------------------------------|
| `pelican_webhook_enabled`        | string  | `'true'` / `'false'`. Conditionne le middleware et la page de logs. |
| `pelican_webhook_token`          | string  | Chiffré via `Crypt::encryptString`. 64 caractères base64 recommandés. |
| `bridge_pelican_webhook_token`   | string  | **Fallback legacy** pour les installs n'ayant pas exécuté la migration d'extraction. Lu par le middleware si `pelican_webhook_token` est manquant. Sera supprimé dans une release future. |
| `mirror_reads_enabled`           | string  | `'true'` / `'false'`. Toggle Phase 2 : quand activé, les controllers (`ServerBackupController`, `ServerDatabaseController`, `ServerNetworkController`) lisent depuis les tables miroirs `pelican_*` au lieu d'appeler Pelican en live. Désactivé par défaut — basculer après avoir lancé `pelican:backfill-mirrors`. |
