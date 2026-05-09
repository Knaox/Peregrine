# Shops — guide d'intégration multi-shop

Peregrine intègre nativement une architecture multi-shop : un nombre
quelconque de shops tiers peuvent vendre tes `ServerConfiguration` en
parallèle, chacun avec sa propre clé API, ses propres webhooks
sortants, et son propre catalogue scopé.

Cette page couvre tout ce qu'un opérateur ou un dev shop doit savoir.

---

## 1. Modèle mental

```
┌─────────────────┐       ┌──────────────────┐       ┌────────────────┐
│ Admin Peregrine │       │  Shop (système   │       │ Client         │
│ (toi)           │       │  de facturation  │       │ (joueur)       │
│                 │       │  tiers)          │       │                │
│ • crée Shops    │       │ • lit ton        │       │ • choisit un   │
│ • mint API keys │       │   catalogue      │       │   plan         │
│ • crée          │       │ • crée des       │       │ • paie Stripe  │
│   ServerConfigs │       │   produits       │       │ • reçoit son   │
│ • attache via   │       │   Stripe         │       │   serveur      │
│   pivot         │       │ • reçoit des     │       │                │
│                 │       │   webhooks       │       │                │
└─────────────────┘       └──────────────────┘       └────────────────┘
```

Tu ne touches jamais aux prix, devises, cycles de facturation ou
trials — c'est le shop qui s'en occupe. Peregrine gère uniquement le
côté technique : "cette configuration = X RAM, Y CPU, cet egg
Pelican, déploie ici."

Le bus qui relie tout ça est **Stripe**. Chaque événement transactionnel
(checkout terminé, subscription renouvelée, refund, dispute) passe par
`/api/stripe/webhook`. Le shop tag chaque transaction Stripe avec quatre
clés metadata `peregrine_*` pour que Peregrine sache quelle
configuration / shop / user / order est concerné.

---

## 2. Configurer un Shop (côté admin)

### A. Créer la fiche Shop

Filament : `/admin/shops` → **Créer**

| Champ | Exemple | Notes |
|---|---|---|
| Nom | `BlueSky Hosting` | Affiché dans Filament + audit Stripe metadata |
| Slug | `bluesky` | Identifiant stable URL-safe |
| Domaine | `bluesky-hosting.com` | Informatif uniquement, aucune auth dérivée |
| Statut | `active` | Suspendre = bloque tout trafic entrant + sortant |

### B. Générer une clé API

Sur la fiche du shop → relation manager **Api Keys** → action
**Generate API key** :

| Champ | Exemple |
|---|---|
| Label | `production-ci` |
| Abilities | `configurations:read`, `orders:read`, `webhooks:read`, `webhooks:write` |
| Expire le | (optionnel) |
| Env | `live` ou `test` |

Le token plaintext (`psk_live_a1b2c3d4...`) est affiché **une seule
fois** dans une modale — copie-le immédiatement. Peregrine ne stocke
que son hash SHA-256. Si tu le perds, révoque + regénère.

Transmets le token au dev du shop via canal sécurisé (Bitwarden,
1Password, email chiffré). Jamais en versionning.

### C. Attacher des `ServerConfiguration`

Sur la fiche du shop → relation manager **Server Configurations** →
**Attacher** :

| Champ | Exemple | Notes |
|---|---|---|
| Configuration | `mc-vanilla-medium` | Choisis dans ta liste Filament |
| Shop external ID | `plan-mc-medium` | Optionnel ; ID interne côté shop |
| Visible | `true` | Cacher de l'API publique sans détacher |
| Sort order | `0` | Ordre dans `GET /api/v1/configurations` |

Une configuration **non** attachée à aucun shop est invisible pour
toutes les clés API — c'est un template admin que tu peux promouvoir
plus tard.

---

## 3. Lire le catalogue (côté shop)

```bash
curl -H "Authorization: Bearer psk_live_..." \
     https://peregrine.example.com/api/v1/configurations
```

```json
{
  "data": [
    {
      "id": 42,
      "internal_name": "mc-vanilla-medium",
      "ram": 4096, "cpu": 200, "disk": 20480, "swap_mb": 0,
      "io_weight": 500, "cpu_pinning": null,
      "egg_id": 5, "nest_id": 1,
      "docker_image": "ghcr.io/pelican-eggs/yolks:java_21",
      "port_count": 1,
      "feature_limits": { "allocations": 1, "backups": 3, "databases": 0 },
      "env_var_mapping": [],
      "pivot": {
        "shop_external_id": "plan-mc-medium",
        "is_visible": true,
        "sort_order": 0
      }
    }
  ],
  "meta": { "next_cursor": null, "prev_cursor": null }
}
```

Pagination cursor : réutilise `meta.next_cursor` tel quel comme
`?cursor=…`.

Autres endpoints lecture :
- `GET /api/v1/shop/me` — infos sur le shop appelant + abilities.
- `GET /api/v1/configurations/{id}` — détail d'une configuration.
- `GET /api/v1/orders/{externalOrderId}` — suivi d'un order spécifique.

La référence complète est auto-générée par Scramble à `/docs/api.json`
et rendue en Swagger UI à `/docs`.

---

## 4. Tagger les transactions Stripe

Chaque Stripe Checkout Session / Subscription / PaymentIntent que le
shop crée **doit** porter ces quatre clés metadata :

| Clé | Type | Source |
|---|---|---|
| `peregrine_configuration_id` | int (string) | Le champ `id` retourné par `GET /configurations` (= `ServerConfiguration.id` upstream côté Peregrine). **N'envoyez PAS la primary key de votre table miroir locale — c'est une autre colonne.** |
| `peregrine_shop_id` | int (string) | Le `Shop.id` du shop (retourné par `GET /shop/me`) |
| `peregrine_user_email` | string | Email de l'acheteur (lowercased + trim) |
| `peregrine_external_order_id` | string | Référence d'order opaque côté shop |

> Si le shop mirror le catalogue localement (pattern recommandé),
> assurez-vous que le builder de metadata lit la colonne qui stocke
> l'id upstream (par ex. `peregrine_configurations.peregrine_id`), pas
> la PK de la row locale. Envoyer la PK locale est la cause la plus
> fréquente de `skipped: unknown_configuration` en prod.

Optionnels :

| Clé | Type | Usage |
|---|---|---|
| `peregrine_server_id` + `is_resubscribe=true` | int + bool | Réactive un `Server` existant au lieu d'en créer un |
| `peregrine_metadata` | JSON string | Bag libre persisté dans `Server.metadata` pour audit |

Le SDK PHP officiel fournit un builder fluent :

```php
use Peregrine\ShopSdk\Stripe\MetadataBuilder;

$session = $stripe->checkout->sessions->create([
    'mode' => 'subscription',
    'line_items' => [[ 'price' => $priceId, 'quantity' => 1 ]],
    'success_url' => 'https://my-shop.example.com/success',
    'cancel_url'  => 'https://my-shop.example.com/cancel',
    'customer_email' => $buyer->email,
    'metadata' => MetadataBuilder::create()
        ->configuration(42)
        ->shop(7)
        ->user($buyer->email)
        ->order('shop-order-1234')
        ->build(),
]);
```

### Que se passe-t-il si la metadata est invalide ?

L'action `ResolveStripeMetadataAction` de Peregrine rejette avec une
raison structurée :

| Raison | Cause |
|---|---|
| `missing_required_metadata` | Une des quatre clés est absente ou vide |
| `unknown_shop` | `peregrine_shop_id` ne match aucune fiche Shop |
| `shop_suspended` | Le shop est en statut `suspended` |
| `unknown_configuration` | `peregrine_configuration_id` ne match aucune fiche |
| `configuration_not_authorised_for_shop` | Pas de pivot |

Peregrine ne renvoie **jamais** 4xx dans ce cas — Stripe rejouerait
l'événement pendant des heures. À la place, le rejet est loggé dans
`stripe_processed_events`, une notif admin part, et la réponse est
`200`. Va voir `/admin/bridge-sync-logs` pour investiguer.

---

## 5. Recevoir les webhooks sortants (côté shop)

À chaque édition d'une `ServerConfiguration` dans Filament, Peregrine
fan-out un POST signé vers chaque `WebhookEndpoint` actif de chaque
Shop attaché à la configuration (et qui souscrit au type d'événement).

### Configurer un endpoint

Soit via Filament (`/admin/webhook-endpoints` → **Créer**), soit via
l'API (la clé shop a besoin de l'ability `webhooks:write`) :

```bash
curl -X POST -H "Authorization: Bearer psk_live_..." \
     -H "Content-Type: application/json" \
     -d '{
       "name": "Catalog sync",
       "url": "https://my-shop.example.com/webhooks/peregrine",
       "subscribed_events": ["configuration.created", "configuration.updated", "configuration.deleted"]
     }' \
     https://peregrine.example.com/api/v1/webhooks/endpoints
```

La réponse 201 contient `meta.signing_secret` **une seule fois**.
Stocke-le.

### Vérifier la signature

Trois headers accompagnent chaque livraison :

```
webhook-id        : <UUID>
webhook-timestamp : <unix seconds>
webhook-signature : v1,<base64 hmac-sha256>
```

Le contenu signé est `{webhook-id}.{webhook-timestamp}.{raw_body}`.

Vérification (SDK PHP) :

```php
use Peregrine\ShopSdk\Webhooks\StandardWebhookVerifier;

$ok = (new StandardWebhookVerifier())->verify(
    $request->header('webhook-id'),
    $request->header('webhook-timestamp'),
    $request->getContent(),
    $request->header('webhook-signature'),
    $endpointSigningSecret,
);
if (! $ok) abort(401);
```

Vérification manuelle (n'importe quel langage) documentée dans
[`/docs/standard-webhooks`](/docs/standard-webhooks).

### Types d'événements

Catalog-only :

| Type | Déclencheur |
|---|---|
| `configuration.created` | Admin crée une `ServerConfiguration` |
| `configuration.updated` | Admin en édite une |
| `configuration.deleted` | Admin en supprime une |

Aucun événement de lifecycle ne passe par les webhooks Peregrine.
L'état de provisioning d'un serveur s'observe via les événements
Stripe directement (le shop les reçoit déjà) plus l'endpoint poll
`GET /api/v1/orders/{external_order_id}`.

### Politique de retry

| Tentative | Délai |
|---|---|
| 1 | immédiat |
| 2 | +60 s |
| 3 | +5 min |
| 4 | +15 min |
| 5 | +30 min |
| 6+ | +60 min (cap) |

Après épuisement de `endpoint.max_retries`, la livraison est marquée
`expired` et apparaît dans `/admin/webhook-deliveries`. L'admin peut
replay manuellement en un clic.

---

## 6. Le lifecycle complet en un schéma

```
1. Shop crée Stripe Product + Checkout Session avec metadata.peregrine_*
2. Le client paie
3. Stripe POST checkout.session.completed à /api/stripe/webhook
4. Peregrine valide la metadata → résout Shop + Configuration via pivot
5. Dispatch chain : LinkPelicanAccountJob → ProvisionServerJob
6. Pelican crée le serveur de manière asynchrone
7. Statut bascule : provisioning → active (via webhook install Pelican)
8. Le client reçoit un email "ton serveur est prêt" de Peregrine

(Tout au long du lifecycle :)
- Les événements Stripe pilotent l'état subscription (active, past_due, deleted, refund, dispute)
- Peregrine réagit : suspend, resume, terminate
- Le shop peut poll GET /api/v1/orders/{external_order_id} à tout moment

(Quand l'admin édite une configuration :)
- L'observer fire → fan-out webhook sortant vers les endpoints souscrits
```

---

## 7. Pièges courants

| Piège | Solution |
|---|---|
| Token leaké dans commit / log | Révoque immédiatement, regénère, rotate les signing secrets |
| Une des quatre clés metadata manquantes | Utilise le `MetadataBuilder` du SDK — il erreur localement avant d'aller chez Stripe |
| Shop suspendu → tous les checkouts rejetés silencieusement | Check `/admin/bridge-sync-logs` pour les raisons `shop_suspended` |
| Configuration pas attachée → 404 / `configuration_not_authorised_for_shop` | Vérifie le pivot dans le relation manager du shop |
| Verifier accepte un vieux timestamp | Règle dure : `abs(now - timestamp) > 300` DOIT rejeter |
| Verifier ne dédoublonne pas `webhook-id` | Ajoute un ledger replay par-shop ; Peregrine ne réutilise jamais un id mais le réseau est faillible |
| URL endpoint pointe sur IP privée | Peregrine refuse (protection DNS rebinding) — utilise une URL publique |

---

## 8. Référence

- [Spec Standard Webhooks](/docs/standard-webhooks)
- [Convention Stripe metadata](/docs/stripe-metadata)
- [Setup webhook receveur Pelican](/docs/pelican-webhook)
- [Référence API v1](/docs/api/v1) (ou Swagger UI live à `/docs/api.json`)
- [Source SDK PHP](/packages/peregrine-shop-sdk)
- [Orchestrateur webhook (WHMCS / Paymenter)](/docs/bridge-webhook-orchestrator)

---

## 9. Install rapide du SDK

```bash
composer require peregrine/peregrine-shop-sdk
```

```php
use Peregrine\ShopSdk\Client;

$client = new Client('https://peregrine.example.com', $token);
$catalog = $client->configurations();
$me = $client->shopMe();
$order = $client->order('shop-order-1234');
$endpoint = $client->createWebhookEndpoint(
    name: 'Catalog sync',
    url: 'https://my-shop.example.com/webhooks/peregrine',
    subscribedEvents: ['configuration.created', 'configuration.updated', 'configuration.deleted'],
);
echo $endpoint['meta']['signing_secret']; // UNE SEULE FOIS — stocke
```

C'est toute l'intégration. Stripe gère l'argent, Peregrine gère les
serveurs, et le shop relie les deux via quatre clés metadata + un
token Bearer.
