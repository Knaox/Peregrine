# Réglages Stripe

Cette page documente `/admin/stripe-settings` — le **seul** endroit où
Peregrine lit ses credentials Stripe et ses URLs côté client. Le reste
(registre multi-shop, webhooks Pelican, orchestrateurs tiers) vit sur
des pages dédiées.

---

## Ce que cette page configure

```
┌─────────────────────────────────────────────────────────────┐
│  Stripe entrant (Peregrine reçoit les événements)           │
│   • Secret de signature webhook  ← REQUIS pour que Stripe   │
│   • Secret API                   ← optionnel, pour outbound │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│  URLs côté client (Peregrine envoie des emails)             │
│   • URL de fallback du Billing Portal                       │
│   • Template d'URL de resubscribe                           │
│   • Période de grâce (jours)                                │
└─────────────────────────────────────────────────────────────┘
```

Quand le **secret de signature webhook** est défini, Peregrine traite
les événements Stripe entrants à `POST /api/stripe/webhook` :
`checkout.session.completed`,
`customer.subscription.{updated,deleted,trial_will_end}`,
`invoice.{paid,payment_failed}`, `charge.{refunded,dispute.created}`.

Quand le **secret API** est défini, Peregrine peut appeler l'API Stripe
en sortie pour récupérer les URLs d'invoice (emails de reçu) et créer
des sessions Customer Portal (emails de lifecycle).

---

## Setup pas à pas

### 1. Récupère ton secret de signature webhook

Dans ton **Dashboard Stripe** :

1. Va sur **Developers → Webhooks**.
2. Clique **Add endpoint**.
3. URL : `https://your-peregrine.example.com/api/stripe/webhook`
4. Choisis **Listen to** → **Events on Connected accounts** si tu
   utilises Connect, sinon **Events on your account**.
5. Sélectionne les événements suivants (ou "All events" si la verbosité
   ne te dérange pas — Peregrine ignore les types non supportés) :
   - `checkout.session.completed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `customer.subscription.trial_will_end`
   - `invoice.paid`
   - `invoice.payment_failed`
   - `charge.refunded`
   - `charge.dispute.created`
6. Clique **Add endpoint**.
7. Sur la page de détail de l'endpoint, clique **Reveal** sous
   **Signing secret**. La valeur commence par `whsec_…`.
8. Copie-la et colle-la dans le champ **Stripe webhook signing secret**
   sur `/admin/stripe-settings`. Sauvegarde.

Vérifie en envoyant un événement de test depuis le Dashboard Stripe —
il doit apparaître dans `/admin/stripe-processed-events` avec status 200.

### 2. (Optionnel) Configure le secret API

Le secret API permet les appels sortants. Requis si tu veux :

- De vrais liens `hosted_invoice_url` dans les emails de reçu (sinon
  fallback sur l'URL du panel).
- Des sessions Customer Portal personnalisées dans les emails
  `ServerSuspended` (sinon fallback sur l'URL statique ci-dessous).

Dans ton **Dashboard Stripe** : **Developers → API keys → Secret key
(reveal)**. Format : `sk_live_…` (ou `sk_test_…`).

Colle dans **Stripe API secret**. Sauvegarde.

### 3. URLs côté client

| Champ | Rôle | Comportement par défaut sans valeur |
|---|---|---|
| URL de fallback Billing Portal | URL statique pointant vers ton Customer Portal hébergé. Utilisée dans les emails quand la création de session API échoue ou qu'aucun secret API n'est configuré. | L'email omet le CTA "Gérer la facturation" |
| Template URL resubscribe | Template URL utilisé dans l'email "votre serveur est suspendu" pour que le client achète une nouvelle subscription. Placeholders : `{server_id}`, `{configuration}`, `{configuration_id}`, `{ts}`, `{signature}`. | L'email omet le CTA "Réactiver" |
| Période de grâce (jours) | Jours conservés entre l'envoi de `customer.subscription.deleted` par Stripe et la suppression effective du serveur. Le client peut resubscribe pendant cette fenêtre. | 14 jours (défaut) |

La signature dans l'URL resubscribe est HMAC-SHA256 sur
`{server_id}|{configuration}|{ts}` keyed avec le legacy
`bridge_shop_shared_secret`. Si tu utilisais le legacy Bridge, c'est
déjà rempli. Sinon le lien part sans signature — la page resubscribe
de ton shop peut ignorer le param.

---

## Référence champ par champ

### Secret de signature webhook Stripe (requis)

- **Où c'est utilisé** : `app/Http/Middleware/VerifyStripeSignature.php`
  passe les payloads entrants par `Stripe\Webhook::constructEvent()`.
- **Stockage** : chiffré au repos via `Crypt::encryptString`. Champ
  vide dans le formulaire = conserver la valeur actuelle (l'admin
  saisit une nouvelle valeur pour la roter).
- **Sans ça** : `/api/stripe/webhook` rejette tous les appels avec 401.

### Secret API Stripe (optionnel)

- **Où c'est utilisé** :
  - `app/Notifications/Bridge/PaymentConfirmedNotification.php`
    récupère le `hosted_invoice_url` pour le CTA reçu.
  - `app/Services/Bridge/Stripe/StripeBillingPortalLinker.php` crée
    une session Stripe Customer Portal par utilisateur.
- **Stockage** : chiffré au repos. Vide = conserver l'actuel.
- **Sans ça** : les helpers email tombent en fallback sur les URLs
  statiques ci-dessous (aucun appel API).

### URL de fallback Billing Portal (optionnel)

- **Où c'est utilisé** : `StripeBillingPortalLinker::urlFor()` retourne
  ça quand aucun secret API n'est configuré ou que la création de
  session échoue.
- **Format** : `https://billing.stripe.com/p/login/...` (URL de ton
  portal hébergé).
- **Sans ça** : l'email omet le lien "Gérer la facturation".

### Template URL resubscribe (optionnel)

- **Où c'est utilisé** :
  `StripeBillingPortalLinker::resubscribeUrlFor()` interpole les
  placeholders et signe le payload, appelé depuis
  `ServerSuspendedNotification`.
- **Placeholders** :
  - `{server_id}` — `Server.id` Peregrine
  - `{configuration}` — `ServerConfiguration.internal_name`
  - `{configuration_id}` — `ServerConfiguration.id`
  - `{ts}` — unix seconds à la génération du lien
  - `{signature}` — HMAC-SHA256 sur `{server_id}|{configuration}|{ts}`
    keyed avec `bridge_shop_shared_secret`
- **Sans ça** : l'email omet le CTA "Réactiver".

### Période de grâce (jours, défaut 14)

- **Où c'est utilisé** : `app/Jobs/SuspendServerJob.php` la lit quand
  il reçoit un événement Stripe `customer.subscription.deleted` ; le
  serveur est suspendu immédiatement + `scheduled_deletion_at` est
  fixé à `now() + grace_period_days`. Le cron quotidien
  `PurgeScheduledServerDeletionsJob` supprime les serveurs au-delà de
  leur grâce.
- **Effet** : pendant la grâce, le client peut `resubscribe` et la
  suppression est annulée.
- **0** : suppression immédiate au prochain cron.

---

## Badges de status sur la page

| Badge | Signification |
|---|---|
| **Stripe webhook configured** (vert) | `bridge_stripe_webhook_secret` est défini. Peregrine accepte les events entrants. |
| **Stripe webhook missing** (orange) | Secret manquant. Les events Stripe sont rejetés avec 401. |
| **Active shop(s)** (vert) | Au moins une fiche Shop avec `status='active'`. |
| **No active shop** (gris) | Aucun shop configuré — la surface API multi-shop renvoie 401 à toutes les clés. |

---

## Ce que cette page NE configure PAS

| Sujet | Où aller |
|---|---|
| Registre multi-shop, clés API, endpoints webhook | [/admin/shops](/admin/shops) — voir [/docs/shops](/docs/shops) |
| Token receveur webhook Pelican | [/admin/pelican-webhook-settings](/admin/pelican-webhook-settings) — voir [/docs/pelican-webhook](/docs/pelican-webhook) |
| Souscriptions webhooks sortants | [/admin/webhook-endpoints](/admin/webhook-endpoints) — voir [/docs/standard-webhooks](/docs/standard-webhooks) |
| Audit des events Stripe entrants | [/admin/stripe-processed-events](/admin/stripe-processed-events) (à venir) |
| Audit des appels Bridge legacy | [/admin/bridge-sync-logs](/admin/bridge-sync-logs) |

---

## Tester les webhooks entrants

### Stripe CLI

```bash
stripe listen --forward-to https://your-peregrine.example.com/api/stripe/webhook
# Puis dans un autre terminal :
stripe trigger checkout.session.completed \
    --add metadata.peregrine_configuration_id=42 \
    --add metadata.peregrine_shop_id=7 \
    --add metadata.peregrine_user_email=test@example.com \
    --add metadata.peregrine_external_order_id=test-order-1
```

L'événement déclenché atterrit à `/api/stripe/webhook`, la signature
est vérifiée, la metadata résolue, le chain
`LinkPelicanAccountJob → ProvisionServerJob` est dispatché. Surveille
les logs du worker queue.

### Raisons de rejet courantes

Quand la metadata n'est pas conforme, Peregrine renvoie `200` (pour
que Stripe ne retry pas) mais logge le rejet dans
`/admin/bridge-sync-logs` :

| Valeur `skipped` | Cause |
|---|---|
| `missing_required_metadata` | Une des quatre clés `peregrine_*` est absente ou vide |
| `unknown_shop` | `peregrine_shop_id` ne match aucune fiche Shop |
| `shop_suspended` | Shop en statut `suspended` |
| `unknown_configuration` | `peregrine_configuration_id` ne match aucune fiche |
| `configuration_not_authorised_for_shop` | Le shop n'est pas attaché à cette configuration via le pivot |

Voir [/docs/stripe-metadata](/docs/stripe-metadata) pour la convention
metadata complète.

---

## Référence

- [Guide intégration multi-shop](/docs/shops)
- [Convention Stripe metadata](/docs/stripe-metadata)
- [Standard Webhooks (spec sortante)](/docs/standard-webhooks)
- [Receveur webhook Pelican](/docs/pelican-webhook)
- [Orchestrateur webhook (WHMCS / Paymenter)](/docs/bridge-webhook-orchestrator)
