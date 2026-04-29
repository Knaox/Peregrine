# Bridge — Orchestrateur webhook (Paymenter, WHMCS, …)

Cette page est le pendant côté opérateur du mode **Bridge — Orchestrateur
webhook** dans Peregrine. Choisissez ce mode dès lors que votre front-shop
est **un système de facturation tiers qui pilote Pelican via son propre
module** et que Pelican renvoie ses events natifs vers Peregrine. Le même
câblage fonctionne pour plusieurs orchestrateurs :

| Orchestrateur | Intégration Pelican | Statut |
|---|---|---|
| **Paymenter** | [Extension Pelican-Paymenter](https://builtbybit.com) (extension payante) | ✅ Testé |
| **WHMCS** | Module [`pelican-dev/whmcs`](https://github.com/pelican-dev/whmcs) (officiel) | ✅ Testé |
| Tout autre système | Doit appeler la Pelican Application API + émettre les events webhook natifs Pelican | ⚠️ Devrait fonctionner — Peregrine est agnostique |

Si à la place vous pilotez le provisioning depuis un shop façon SaaSykit
avec Stripe qui parle directement à Peregrine, utilisez plutôt **Bridge
Shop + Stripe** — voir `/docs/bridge-api`. Les deux modes sont mutuellement
exclusifs.

## L'architecture en un schéma

```
[Customer] --buys plan--> [Orchestrator] --provisions--> [Pelican Panel]
                              |                              |
                              | (emails customer, billing,   | (webhooks: created /
                              |  upgrades, suspensions)      |  updated / deleted)
                              v                              v
                        Customer mailbox            POST /api/pelican/webhook
                                                          |
                                                          v
                                                    [Peregrine]
                                                  mirrors local DB,
                                                  no email is sent.
```

Notes :

- L'orchestrateur est la **source de vérité unique** pour la facturation,
  les plans et la communication client.
- Peregrine **n'envoie jamais** d'email "serveur prêt" / "serveur
  suspendu" dans ce mode — l'orchestrateur le fait déjà.
- La page admin **Plans** de Peregrine est masquée dans ce mode
  (l'orchestrateur gère le catalogue).
- Le récepteur Peregrine est **agnostique** vis-à-vis de la marque de
  l'orchestrateur : il consomme les events Pelican bruts, peu importe qui
  les a déclenchés en amont.

## Compatibilité

### Paymenter

Une option d'identity provider OAuth gratuite est aussi disponible (voir
`/docs/authentication`). L'extension Pelican-Paymenter est hébergée sur
builtbybit.com — installez-la sur Paymenter et configurez votre URL de
base Pelican + clé API admin dans les paramètres de l'extension.

L'extension crée les utilisateurs Pelican à la première activation de
service, puis provisionne les serveurs sous cet utilisateur. `external_id`
sur le serveur Pelican est positionné à l'id de service Paymenter.

### WHMCS

WHMCS utilise le [module officiel `pelican-dev/whmcs`](https://github.com/pelican-dev/whmcs).
Installez-le comme module serveur sous **Setup → Products/Services**, puis
configurez votre URL de base Pelican + token Application API (`papp_…`) +
node par défaut + egg.

Cycle de vie :

| Action WHMCS | Conséquence Pelican | Ce que voit Peregrine |
|---|---|---|
| Service activé (achat client) | Serveur créé | `created: Server` (et `created: User` si l'utilisateur n'existait pas encore) |
| Service suspendu (impayé) | Serveur suspendu | `updated: Server` (status → suspended) |
| Service réactivé (paiement récupéré) | Serveur réactivé | `updated: Server` (status → active) |
| Service résilié | Serveur supprimé | `deleted: Server` |

`external_id` sur le serveur Pelican est positionné à l'**id de service
WHMCS**. Il est mirroré localement comme `paymenter_service_id` (nom
hérité conservé pour la rétrocompatibilité — sémantique = "External
service ID").

Le single sign-on OAuth pour WHMCS est aussi disponible via le provider
OpenID Connect natif de WHMCS — voir `/docs/whmcs-oauth-setup`.

### Autres systèmes

Le récepteur est totalement agnostique. Tout système qui utilise la
Pelican Application API pour créer / suspendre / supprimer des serveurs
déclenchera les mêmes events Pelican natifs, que Peregrine consomme à
l'identique. Les seuls éléments spécifiques à un orchestrateur sont les
prérequis (le plugin d'intégration côté orchestrateur) — rien dans
Peregrine lui-même.

## Prérequis

| Composant | Version minimale | Pourquoi |
|---|---|---|
| Pelican Panel | 0.46+ | Webhooks sortants natifs (`/admin/webhooks`) |
| Intégration orchestrateur | dernière version stable | Crée / suspend les serveurs via Pelican sur les events de cycle de vie |
| Worker queue Peregrine | en cours d'exécution | Le handler webhook dispatche des jobs ; sans worker, rien n'est mirroré |

Si votre Pelican est antérieur à 0.46, **l'UI webhooks n'existe pas
encore** — mettez à jour Pelican avant d'activer ce mode. La
réconciliation par polling de secours ne peut pas remplacer à elle seule
les webhooks (un délai de 5 minutes est trop élevé pour un flux de
checkout en production).

## 1. Générer le token Peregrine

Ouvrez l'admin Peregrine sur `/admin/bridge-settings` :

1. Mettez **Active bridge backend** sur **Webhook-orchestrated (Paymenter,
   WHMCS, …)**.
2. Dépliez la section **Bridge — Webhook orchestrator**.
3. Cliquez sur l'icône 🔑 à côté de **Pelican webhook authentication
   token** pour générer un nouveau token aléatoire de 64 caractères.
4. Copiez la valeur affichée dans votre presse-papiers.
5. Cliquez sur **Save Settings**.

Notes :

- Le token est chiffré au repos (`Crypt::encryptString`).
- Sauvegarder une valeur vide conserve le token existant. Pour faire une
  rotation, générez-en un nouveau et mettez à jour les headers Peregrine
  *et* Pelican en parallèle.
- **Ne perdez pas le token en cours de rotation** : Peregrine ne réaffiche
  pas la valeur stockée, il ne fait que chiffrer la nouvelle entrée.

## 2. Configurer le webhook sortant Pelican

Ouvrez l'admin Pelican sur `/admin/webhooks` et cliquez sur **Create
Webhook**.

### Champs principaux

| Champ | Valeur |
|---|---|
| **Type** | `Regular` (l'autre option, *Discord*, formaterait le body comme un message Discord — incorrect pour notre usage) |
| **Description** | `Peregrine mirror — webhook orchestrator` (texte libre, affiché uniquement dans l'admin Pelican) |
| **Endpoint** | `https://YOUR-PEREGRINE-DOMAIN/api/pelican/webhook` (sans slash final) |

### Headers

Pelican pré-remplit une ligne par défaut — **gardez-la telle quelle**, puis
ajoutez une seconde ligne pour le bearer token :

| Clé | Valeur | Pourquoi |
|---|---|---|
| `X-Webhook-Event` | `{{event}}` | Template Pelican — substitue le nom de l'event (ex. `created: Server`). Peregrine lit ce header comme identifiant canonique de l'event. |
| `Authorization` | `Bearer <token de l'étape 1>` | Authentification — Peregrine rejette en 401 si absent ou erroné. |

Pas besoin de positionner `Content-Type` — Pelican envoie toujours
`application/json`.

### Events

Utilisez la barre de recherche pour trouver chaque entrée, puis cochez la
case. Les **cinq** events à activer :

| Label Pelican | Quand il se déclenche | Effet sur Peregrine |
|---|---|---|
| `created: Server` | L'orchestrateur a activé un service → Pelican a créé le serveur | Ligne `Server` locale créée, status `provisioning` |
| `updated: Server` | Suspend / unsuspend / rename / changement de limites build / install terminée | Status synchronisé (`provisioning` → `active`, `active` → `suspended`, etc.), nom mis à jour |
| `deleted: Server` | L'orchestrateur a résilié / annulé le service | Ligne locale supprimée |
| `created: User` | L'orchestrateur a créé un nouveau client Pelican | Ligne `User` mirror locale créée (sans password) |
| `event: Server\Installed` | Signal de fin d'installation | Status bascule de `provisioning` à `active` instantanément |

> ℹ️ **Cochez bien `event: Server\Installed` ET `updated: Server`.**
> `event: Server\Installed` est le signal canonique de fin d'installation
> que Pelican déclenche dès que le script d'installation termine ;
> `updated: Server` est un signal secondaire (Pelican passe la colonne
> `status` de `"installing"` à `null` au même moment) et sert de filet
> de sécurité si le premier event est perdu pour une raison quelconque.

Cliquez sur **Save**. Pelican commencera à délivrer les events au
prochain changement d'état éligible.

## 3. Vérifier le câblage

Le test le plus propre : créez un service dans votre orchestrateur depuis
un vrai plan, observez l'aller-retour.

1. Depuis votre UI client (Paymenter / WHMCS / …), commandez un plan
   serveur et terminez le flux de checkout.
2. L'orchestrateur active le service → son module Pelican crée le serveur
   dans Pelican → Pelican émet `eloquent.created: App\Models\Server`.
3. En quelques secondes, `/admin/servers` dans Peregrine affiche le
   nouveau serveur avec le status `provisioning`.
4. Une fois l'installation terminée, Pelican émet
   `App\Events\Server\Installed` → le status bascule sur `active`.
5. Suspendez le service depuis votre orchestrateur → le status passe à
   `suspended`.
6. Réactivez → le status revient à `active`.
7. Résiliez le service → la ligne locale est supprimée.

Auditez chaque aller-retour dans `/admin/pelican-webhook-logs`
(ressource Filament visible uniquement en mode orchestrateur webhook).

## 4. Limites & notes opérationnelles

### Pelican ne RETRY pas

Pelican émet ses webhooks sortants **une seule fois**. Si Peregrine
retourne un non-2xx ou est injoignable, l'event est perdu. Pour
compenser, Peregrine exécute une **passe de réconciliation toutes les 5
minutes** : le `SyncServerStatusJob` existant compare la liste complète
des serveurs Pelican avec la table locale et corrige les divergences
(crée les lignes manquantes, supprime les orphelines).

Cela signifie qu'au pire le délai est de 5 minutes — acceptable pour un
provisioning en production, mais pas idéal pour l'UX. Si vous voyez des
réconciliations créer des serveurs fréquemment, investiguez pourquoi vos
webhooks échouent (token incorrect ? worker queue mort ? panne
Peregrine ?).

### Pelican ne SIGNE PAS les payloads

Il n'y a pas de schéma HMAC natif. L'authentification repose entièrement
sur le bearer token. Traitez-le comme un mot de passe :

- Faites une rotation après toute fuite.
- Ne le collez jamais en chat / tickets.
- La route est rate-limitée à 240 req/min/IP — du brute-forcing lent
  coûte aussi cher en travail de queue.

### Aucun email n'est envoyé depuis Peregrine

Le mode orchestrateur webhook n'émet jamais les events `ServerProvisioned`
ni `ServerSuspended`. Les templates email Bridge
(`bridge_server_ready_*`, `bridge_server_suspended`) sont filtrés hors de
`/admin/email-templates` dans ce mode. Votre orchestrateur (Paymenter,
WHMCS, …) envoie lui-même tous les emails destinés au client.

### Mirroring d'`external_id`

Pelican stocke l'id de service de l'orchestrateur dans le champ
`external_id` de chaque Server. Peregrine le surface localement comme
`paymenter_service_id` (nom de colonne hérité — sémantiquement c'est
*External service ID*) pour les flux d'audit et de support. Il n'est
**jamais** utilisé comme clé fonctionnelle — l'identifiant canonique
reste `pelican_server_id`.

Pour Paymenter : l'id de service Paymenter.
Pour WHMCS : l'id de service WHMCS (`tblhosting.id`).

## 5. Dépannage

| Symptôme | Diagnostic | Correctif |
|---|---|---|
| `/api/pelican/webhook` retourne 401 | Token incorrect | Recollez le token depuis `/admin/bridge-settings` dans les headers du webhook Pelican. |
| `/api/pelican/webhook` retourne 503 | `bridge_mode !== paymenter` (l'ancienne valeur d'enum, affichée comme "Webhook orchestrator") | Basculez le radio dans `/admin/bridge-settings` sur l'option orchestrateur webhook et sauvegardez. |
| Les serveurs n'apparaissent pas dans Peregrine | Worker queue à l'arrêt | `php artisan queue:work` (voir `docs/operations/queue-worker.md`). Le cron de réconciliation rattrapera après le redémarrage du worker. |
| Même event mirroré deux fois | Pelican a réémis lors d'une reconfiguration du webhook | L'idempotence est indexée sur `sha256(event\|model_id\|updated_at\|body)` — les doublons court-circuitent silencieusement. Rien à faire. |
| Serveur supprimé encore visible | Pelican n'a jamais émis l'event delete | Le cron de réconciliation supprime les lignes locales orphelines en moins de 5 min. |
| Serveur WHMCS orphelin (pas de client correspondant) | Le coche de `created: User` a manqué l'event de création de l'utilisateur | Soit cochez `created: User` et déclenchez une sync manuelle depuis WHMCS, soit faites le matching par email a posteriori via `/admin/users`. |

Pour inspecter manuellement chaque webhook que Peregrine a accepté :

- UI : `/admin/pelican-webhook-logs`
- DB : `select event_type, pelican_model_id, response_status, processed_at, error_message from pelican_processed_events order by processed_at desc limit 50;`

## 6. Repasser à Bridge Shop + Stripe

Si vous migrez un jour de l'orchestrateur vers une configuration SaaSykit
/ Stripe direct :

1. Dans `/admin/bridge-settings`, changez **Active bridge backend** vers
   **Shop + Stripe**.
2. Sauvegardez. Le webhook Pelican commencera à retourner 503
   (comportement correct — vous n'êtes plus en mode orchestrateur
   webhook).
3. Désactivez ou supprimez la configuration webhook Pelican dans
   `/admin/webhooks` pour stopper les tentatives de livraison vouées à
   l'échec.
4. Configurez la section Shop + Stripe comme documenté dans
   `/docs/bridge-api`.
5. Les serveurs déjà mirrorés restent dans votre DB mais ne reçoivent
   plus de mises à jour de cycle de vie via les webhooks Pelican ; vous
   pouvez les nettoyer manuellement si besoin.
