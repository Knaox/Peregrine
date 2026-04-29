# API Bridge Peregrine — pour les développeurs shop

Ce document décrit le contrat HTTP exposé par Peregrine pour les **shops externes**
(SaaSykit, Paymenter, plateformes de facturation custom Laravel/Node/Python…) afin
de pousser des définitions de plans dans une instance Peregrine.

L'admin Peregrine détient la configuration technique (egg Pelican, node, image
docker, mapping des ports, toggles runtime). Votre shop détient la configuration
métier (nom, prix, RAM/CPU/disque promis au client). Le Bridge maintient ces deux
côtés alignés sans coupler l'un des codebases à l'autre.

## Architecture

```
SHOP                            PEREGRINE                      STRIPE
  │                                 │                            │
  │  POST /api/bridge/plans/upsert  │                            │
  ├────────────────────────────────▶│  ← stores plan mirror      │
  │  HMAC-signed JSON payload       │    in `server_plans`       │
  │                                 │                            │
  │  DELETE /api/bridge/plans/{id}  │                            │
  ├────────────────────────────────▶│  ← deactivates plan        │
  │                                 │                            │
  │                                 │  POST /webhook/stripe (P3) │
  │                                 │◀───────────────────────────┤
```

Les endpoints du Bridge sont **server-to-server** : votre shop appelle Peregrine
directement. Aucune authentification utilisateur n'est impliquée — l'appel est
authentifié par une signature HMAC partagée entre les deux systèmes.

Le provisioning côté Stripe passe par un canal séparé (webhook Stripe vers
Peregrine) — hors du périmètre de ce document.

## Configuration client

L'admin de votre shop exposera typiquement un écran de paramètres pour configurer
le client Bridge. **Configurez uniquement l'URL de base** de l'instance
Peregrine — votre code client ajoute automatiquement les chemins des endpoints.

✅ **Correct** — configurer une URL de base :

```
https://peregrine.example.com
```

❌ **Incorrect** — ne jamais inclure un chemin d'endpoint :

```
https://peregrine.example.com/api/bridge/plans/upsert
https://peregrine.example.com/api/bridge/
https://peregrine.example.com/api/bridge/plans/upsert/
```

Faire la mauvaise chose produit des URLs aberrantes comme
`https://peregrine.example.com/api/bridge/plans/upsert/api/bridge/ping` →
HTTP 404. Une implémentation client robuste devrait soit (a) valider et
rejeter toute URL configurée contenant `/api/bridge/`, soit (b) supprimer les
segments de chemin en suffixe avant stockage. L'un ou l'autre comportement est
préférable à accepter silencieusement une URL mal configurée.

## Authentification

Chaque requête Bridge doit transporter deux headers :

| Header | Format | Rôle |
|---|---|---|
| `X-Bridge-Signature` | `sha256=<hex digest>` | HMAC-SHA256 du body brut de la requête, avec le secret partagé |
| `X-Bridge-Timestamp` | `<unix milliseconds>` | Horodatage de la requête. Protection anti-replay : Peregrine rejette les timestamps décalés de plus de 5 min |

Le secret partagé est généré par l'admin Peregrine dans
**Admin → Bridge** (un nouveau secret base64 de 64 caractères est à un clic).
Copiez la valeur et configurez-la côté shop.

### Algorithme de signature

```
signature = "sha256=" + lowercase_hex( HMAC-SHA256( raw_body, shared_secret ) )
```

Utilisez le **body brut de la requête** (les bytes exacts que vous allez envoyer),
pas une version re-sérialisée. L'ordre des clés JSON doit être stable entre la
signature et l'envoi.

### Timing de vérification

Peregrine compare les signatures via `hash_equals()` (timing-safe). Votre client
doit faire de même pour éviter des fuites par timing s'il valide un jour les
réponses de Peregrine.

## Endpoints

### `POST /api/bridge/ping`

Health check no-op. À utiliser depuis le bouton « Test connection » de votre shop
pour vérifier que l'URL + le secret partagé sont corrects sans créer de données
côté Peregrine.

**Headers** : identiques à n'importe quel appel Bridge (signature + timestamp).
Le body doit être un JSON vide `{}` (signez le JSON vide `{}`).

**Réponse** (200 OK) :

```json
{
  "ok": true,
  "service": "peregrine-bridge",
  "version": "1.0",
  "received_at": "2026-04-22T18:00:00+00:00"
}
```

Un 200 ici signifie : URL joignable, signature valide, Bridge activé. Aucune
écriture en base, aucune entrée de log d'audit. Vous pouvez l'appeler aussi
souvent que nécessaire.

### `POST /api/bridge/plans/upsert`

Créer un plan ou rafraîchir un plan existant. Idempotent sur `shop_plan_id` —
renvoyer le même payload ne crée **pas** de doublons.

**Headers** :
```
Content-Type: application/json
X-Bridge-Signature: sha256=<hex>
X-Bridge-Timestamp: <ms>
```

**Body** (JSON) :

```json
{
  "shop_plan_id": 42,
  "shop_plan_slug": "minecraft-4go",
  "shop_plan_type": "subscription",
  "name": "Minecraft 4Go",
  "description": "A 4GB Minecraft server with Paper and 5 plugins preinstalled",
  "is_active": true,

  "billing": {
    "price_cents": 700,
    "currency": "CHF",
    "interval": "month",
    "interval_count": 1,
    "has_trial": false,
    "trial_interval": null,
    "trial_interval_count": null,
    "stripe_price_id": "price_1QXxxxxx"
  },

  "pelican_specs": {
    "ram_mb": 4096,
    "swap_mb": 0,
    "disk_mb": 10240,
    "cpu_percent": 200,
    "io_weight": 500,
    "cpu_pinning": null
  },

  "checkout": {
    "custom_fields": [
      {
        "key": "server_name",
        "label": "Server name",
        "type": "text",
        "optional": false
      }
    ]
  }
}
```

**Référence des champs** :

| Champ | Requis | Type | Notes |
|---|---|---|---|
| `shop_plan_id` | oui | int | Identifiant unique stable côté shop. Sert de clé d'upsert |
| `shop_plan_slug` | oui | string ≤ 255 | Slug pour l'affichage (ex. fragment d'URL) |
| `shop_plan_type` | oui | enum | `subscription` ou `one_time` |
| `name` | oui | string ≤ 255 | Nom destiné au client |
| `description` | non | string | Description en texte libre |
| `is_active` | oui | bool | Si false → plan inactif dans Peregrine, plus de nouveaux serveurs |
| `billing.price_cents` | oui | int | Prix en centimes (ex. 700 pour 7,00) |
| `billing.currency` | oui | string(3) | ISO 4217 (CHF, EUR, USD…) |
| `billing.interval` | requis si subscription | enum | `day` / `week` / `month` / `year` |
| `billing.interval_count` | requis si subscription | int ≥ 1 | Multiplicateur (1 mois, 3 mois…) |
| `billing.has_trial` | oui | bool | |
| `billing.trial_interval`, `trial_interval_count` | conditionnel | | Quand `has_trial` true |
| `billing.stripe_price_id` | non | string | Renseigné quand le shop a synchronisé ce plan vers Stripe |
| `pelican_specs.ram_mb` | oui | int ≥ 128 | RAM en Mo |
| `pelican_specs.swap_mb` | non (défaut 0) | int ≥ 0 | |
| `pelican_specs.disk_mb` | oui | int ≥ 256 | |
| `pelican_specs.cpu_percent` | oui | int ≥ 1 | 100 = 1 cœur, 200 = 2 cœurs… |
| `pelican_specs.io_weight` | non (défaut 500) | int 10-1000 | Priorité I/O Pelican |
| `pelican_specs.cpu_pinning` | non | string | Pinning style `0,1,2`, ou null |
| `checkout.custom_fields[]` | non, max 3 | array | Champs custom natifs Stripe Checkout |
| `checkout.custom_fields[].key` | requis | string ≤ 40 alphaDash | |
| `checkout.custom_fields[].label` | requis | string ≤ 100 | |
| `checkout.custom_fields[].type` | requis | enum | `text` / `numeric` / `dropdown` |
| `checkout.custom_fields[].optional` | requis | bool | |

**Réponse** (200 OK) :

```json
{
  "peregrine_plan_id": 7,
  "synced_at": "2026-04-22T18:00:00+00:00",
  "status": "needs_admin_config"
}
```

`status` vaut soit `ready` (egg + node configurés par l'admin Peregrine — le
plan peut provisionner des serveurs) soit `needs_admin_config` (l'admin doit
finir de configurer le côté technique avant qu'un client puisse acheter).

### `DELETE /api/bridge/plans/{shop_plan_id}`

Désactivation soft d'un plan (fixe `is_active = false`). Les serveurs déjà
provisionnés à partir de ce plan conservent leur référence — seuls les
**nouveaux** achats sont bloqués.

**Headers** : identiques à upsert (signature + timestamp). Le body est vide —
signez la chaîne vide.

**Réponses** :

| Statut | Body | Quand |
|---|---|---|
| 200 | `{"deactivated_at":"<iso>"}` | Le plan a été trouvé et désactivé |
| 404 | `{"error":"plan_not_found"}` | Aucun plan avec ce `shop_plan_id` |

## Réponses d'erreur

Toutes les réponses d'erreur sont du JSON `{"error": "<key>"}`. La liste des
clés :

| HTTP | Clé | Signification |
|---|---|---|
| 401 | `bridge.invalid_signature` | `X-Bridge-Signature` manquant ou erroné |
| 410 | `bridge.invalid_timestamp` | `X-Bridge-Timestamp` manquant ou non parseable |
| 410 | `bridge.timestamp_expired` | Timestamp en dehors de la fenêtre anti-replay de 5 min |
| 422 | (objet d'erreurs de validation Laravel) | Le payload ne correspond pas au schéma. Consultez le champ `errors` pour le détail par champ |
| 429 | (throttle Laravel) | Limite de débit par IP dépassée (60 req/min) |
| 503 | `bridge.disabled` | Le Bridge est désactivé dans l'admin Peregrine |
| 503 | `bridge.secret_not_configured` | Le secret partagé n'a pas été défini dans l'admin Peregrine |

## Sémantique de mise à jour

Quand vous re-poussez un plan (même `shop_plan_id`), Peregrine **n'écrase que les
champs détenus par le shop** (tout ce qui figure dans le payload ci-dessus). La
configuration technique posée par l'admin Peregrine (egg, node, docker_image,
mapping des ports, env vars, toggles runtime, feature limits) est **préservée**.
Cela signifie :

- Le shop peut changer le prix d'un plan librement — la configuration de
  provisioning du serveur existant reste intacte.
- L'admin Peregrine peut ajuster egg/docker/etc. sans craindre d'être écrasé
  à la prochaine sync.
- Propriété à sens unique : chaque champ a exactement une source de vérité.

## Exemples de code

### PHP (Guzzle)

```php
use GuzzleHttp\Client;

$secret = 'your-shared-secret-from-peregrine-admin';
$payload = json_encode([
    'shop_plan_id' => 42,
    // … rest of the payload
], JSON_THROW_ON_ERROR);

$timestamp = (int) (microtime(true) * 1000);
$signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

$response = (new Client())->post('https://peregrine.example.com/api/bridge/plans/upsert', [
    'headers' => [
        'Content-Type'      => 'application/json',
        'X-Bridge-Signature' => $signature,
        'X-Bridge-Timestamp' => (string) $timestamp,
    ],
    'body' => $payload,
]);
```

### Node.js (fetch)

```javascript
import { createHmac } from 'node:crypto';

const secret = process.env.PEREGRINE_BRIDGE_SECRET;
const payload = JSON.stringify({
    shop_plan_id: 42,
    // … rest of the payload
});

const timestamp = Date.now();
const signature = 'sha256=' + createHmac('sha256', secret).update(payload).digest('hex');

const response = await fetch('https://peregrine.example.com/api/bridge/plans/upsert', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-Bridge-Signature': signature,
        'X-Bridge-Timestamp': String(timestamp),
    },
    body: payload,
});
```

### Python (requests)

```python
import hmac, hashlib, json, time, requests

secret = "your-shared-secret"
payload = json.dumps({
    "shop_plan_id": 42,
    # ... rest of the payload
}, separators=(',', ':'))

timestamp = int(time.time() * 1000)
signature = "sha256=" + hmac.new(
    secret.encode(), payload.encode(), hashlib.sha256
).hexdigest()

response = requests.post(
    "https://peregrine.example.com/api/bridge/plans/upsert",
    data=payload,
    headers={
        "Content-Type": "application/json",
        "X-Bridge-Signature": signature,
        "X-Bridge-Timestamp": str(timestamp),
    },
)
```

### cURL (smoke test)

```bash
SECRET='your-shared-secret'
PAYLOAD='{"shop_plan_id":1,"shop_plan_slug":"test","shop_plan_type":"subscription",...}'
TS=$(($(date +%s)*1000))
SIG="sha256=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')"

curl -X POST https://peregrine.example.com/api/bridge/plans/upsert \
  -H "Content-Type: application/json" \
  -H "X-Bridge-Signature: $SIG" \
  -H "X-Bridge-Timestamp: $TS" \
  -d "$PAYLOAD"
```

## Notes opérationnelles

- **Limite de débit** : 60 requêtes/minute par IP source. Les syncs en masse de
  gros catalogues doivent batcher + temporiser en conséquence.
- **Piste d'audit** : chaque appel Bridge (succès OU échec) est journalisé dans
  l'admin Peregrine sous **Settings → Bridge sync logs**. Utilisez cette page
  pour debugger les échecs HMAC ou les soucis de validation de payload.
- **Idempotence** : il est sûr de retenter un upsert avec le même `shop_plan_id`
  autant de fois que voulu.
- **Scrubbing** : si vos `checkout.custom_fields[]` transportent un jour un
  champ `value` (donnée saisie par l'utilisateur capturée au checkout),
  Peregrine le retire avant de persister dans le log d'audit. Le schéma
  n'accepte pas de champ `value` — c'est une protection de compat-future.
- **Ordre des opérations** : un plan peut être poussé avant d'être synchronisé
  vers Stripe (`billing.stripe_price_id` peut être null). Dans ce cas, le plan
  existe dans Peregrine mais `status: needs_admin_config` jusqu'à ce que
  l'admin Peregrine y rattache un egg/node. Renseignez le Stripe Price ID
  dans un `upsert` ultérieur dès que votre shop l'a.

## Webhook Stripe (canal séparé)

Le provisioning d'un vrai serveur de jeu quand un client paie passe par les
webhooks Stripe pointant sur `POST /api/stripe/webhook`. Ceci est
**indépendant** de l'API plan-sync ci-dessus — Stripe envoie les events
directement à Peregrine.

### Événements écoutés par Peregrine

| Événement | Conséquence |
|---|---|
| `checkout.session.completed` | Un nouveau serveur est provisionné (Pelican createServer + ligne Server locale) et le client est notifié par email quand il est prêt. |
| `customer.subscription.updated` | Si le prix a changé → les ressources du serveur sont upgradées/downgradées via Pelican `updateServerBuild`. Si le statut passe à `past_due` → soft suspend. Si le statut revient à `active` → unsuspend. |
| `customer.subscription.deleted` | Le serveur est suspendu immédiatement et programmé pour suppression définitive après la période de grâce (configurable par l'admin, défaut 14 jours). Le client reçoit un email avec la date de suppression. |
| `invoice.payment_failed` | Notification admin (informative). Stripe gère lui-même la politique de retry de relance ; la suspension effective du serveur découle du `subscription.updated → past_due` qui suit. |

### Configuration

1. **Dans Peregrine** : allez dans **Admin → Bridge** et activez le toggle Bridge.
2. **Dans le Stripe Dashboard** : allez dans **Developers → Webhooks → Add endpoint**.
3. URL d'endpoint : `https://{your_peregrine_domain}/api/stripe/webhook`
4. Événements à activer : les 4 ci-dessus (vous pouvez copier-coller `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.payment_failed`).
5. Après création de l'endpoint, cliquez sur « Reveal signing secret » → copiez la valeur `whsec_…`.
6. De retour dans **Peregrine → Admin → Bridge**, collez le secret dans « Stripe webhook signing secret » → Save.
7. (Optionnel) Ajustez **Grace period before hard delete** (défaut 14 jours, plage 0–90).

### Développement local avec la CLI Stripe

```bash
stripe login
stripe listen --forward-to https://your-peregrine-dev.test/api/stripe/webhook
# → CLI prints a temporary whsec_… → paste in /admin/bridge-settings
stripe trigger checkout.session.completed
```

Pour déclencher un flow de bout en bout avec un Price ID spécifique correspondant
à l'un de vos `ServerPlan.stripe_price_id` locaux :

```bash
stripe trigger checkout.session.completed \
  --override checkout_session:line_items[0][price]=price_YOUR_REAL_ID \
  --override checkout_session:metadata[server_name]=MyTestServer
```

### ⚠️ Worker de queue requis

Le handler de webhook **dispatche des jobs** (provisioning, suspension, upgrades
de plan) — il n'appelle jamais Pelican de façon synchrone, puisque Stripe ne
laisse que 5 secondes max pour répondre. **Un worker de queue doit tourner**
pour que le travail réel soit effectué :

```bash
php artisan queue:work --queue=default --tries=3 --max-time=3600
```

Sans worker, les appels webhook réussissent (Stripe voit 200) mais les jobs
s'empilent dans la table `jobs` et ne sont jamais traités. Voir
[docs/operations/queue-worker.md](operations/queue-worker.md) pour le setup
de production avec supervisor / systemd.

### Idempotence

Stripe peut retenter un événement jusqu'à 3 jours en cas d'échec de livraison
(ou occasionnellement re-livrer après un 200). Peregrine maintient un ledger
`stripe_processed_events` indexé sur `event.id` — les doublons court-circuitent
avec `200 {"received":true,"idempotent":true}` et zéro effet de bord. Le ledger
est auto-nettoyé (commande artisan `stripe:clean-processed-events`, schedulée
quotidiennement) des lignes plus vieilles que 30 jours.

## Changelog

- **2026-04-23** : intégration webhook Stripe (P3) en service. Nouvel endpoint
  `POST /api/stripe/webhook` qui accepte les 4 événements de cycle de vie
  (`checkout.session.completed`, `customer.subscription.updated`,
  `customer.subscription.deleted`, `invoice.payment_failed`). Le provisioning
  est mis en queue et idempotent ; les abonnements annulés déclenchent un soft
  suspend + une suppression définitive programmée avec une période de grâce
  configurable par l'admin.
- **2026-04-22** : API plan-sync Bridge initiale. Endpoints `POST
  /api/bridge/ping`, `POST /api/bridge/plans/upsert`, et `DELETE
  /api/bridge/plans/{id}`. Ajout de la section « Configuration client » pour
  prévenir l'erreur courante de configurer une URL d'endpoint complète au lieu
  de l'URL de base.
