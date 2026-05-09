# Orchestrateur webhook (Paymenter, WHMCS, …)

Certains opérateurs n'utilisent pas de shop Stripe-driven ; à la
place, un système de facturation tiers (Paymenter, WHMCS, …) provisionne
le panel Pelican directement via l'API Application de Pelican. Dans
ce setup, **Peregrine se contente de mirror l'état Pelican** pour que
les admins gardent un dashboard serveur unifié.

Cette page explique comment câbler les webhooks sortants de
l'orchestrateur pour que Peregrine reste à jour.

| Orchestrateur | Intégration Pelican | Testé |
|---|---|---|
| **Paymenter** | [Pelican-Paymenter extension](https://builtbybit.com) | ✅ |
| **WHMCS** | [`pelican-dev/whmcs`](https://github.com/pelican-dev/whmcs) | ✅ |
| Autre chose | N'importe quoi qui appelle l'API Application Pelican et émet les events natifs Pelican | ⚠ Devrait marcher — Peregrine est agnostique |

Si ton provisioning est Stripe-driven à la place (Peregrine reçoit
`checkout.session.completed`), voir [`/docs/shops`](/docs/shops). Les
deux flows peuvent coexister : un panel Pelican peut avoir des
serveurs créés par le flow Stripe de Peregrine et d'autres par ton
système de facturation — chaque classe de serveurs reste intouchée
par l'autre.

---

## Architecture

```
[Client] --achète--> [Orchestrateur] --provisionne--> [Panel Pelican]
                          |                                  |
                          | (emails, facturation,            | (webhooks: created,
                          |  upgrades, suspensions)          |  updated, deleted)
                          v                                  v
                  Boîte mail client            POST /api/pelican/webhook
                                                          |
                                                          v
                                                    [Peregrine]
                                                  mirror la DB locale,
                                                  aucun email envoyé.
```

L'orchestrateur est la **source de vérité unique** pour la
facturation, les plans, et la communication client. Peregrine
**n'envoie jamais** d'email "serveur prêt" ou "serveur suspendu"
pour ces serveurs — l'orchestrateur le fait déjà. Le rôle de
Peregrine est :

- Mirror le serveur en local pour qu'il apparaisse dans
  `/admin/servers` et le `/dashboard` du joueur.
- Refléter les transitions de statut (installing → active →
  suspended → deleted) via le webhook Pelican.
- Surfacer les événements install/incident Pelican dans l'UI joueur.

---

## Câblage (peu importe l'orchestrateur)

Le flow est identique quel que soit l'orchestrateur :

### 1. Activer le receveur Pelican de Peregrine

Le receveur webhook Pelican de Peregrine est **toujours actif** — il
ne dépend d'aucun "mode" ni d'aucune config shop. Pour l'activer :

1. Va sur `/admin/pelican-webhook-settings` (Filament).
2. Bascule **Enabled** = on.
3. Clique **Generate token** (ou colle un token existant).
4. Copie le token affiché — il ne sera plus jamais affiché. Stocke-le
   dans ton gestionnaire de mots de passe.

L'URL de l'endpoint est :

```
POST https://your-peregrine.example.com/api/pelican/webhook
```

### 2. Configurer Pelican pour forwarder les événements

Dans le panel Pelican, **Application API → Webhooks** :

| Champ | Valeur |
|---|---|
| URL | `https://your-peregrine.example.com/api/pelican/webhook` |
| Events | `eloquent.created: App\Models\Server`, `eloquent.updated: App\Models\Server`, `eloquent.deleted: App\Models\Server` (et compagnie — voir docs Pelican) |
| Authentification | `Bearer <le token de l'étape 1>` |

### 3. Configurer l'orchestrateur

Chaque orchestrateur a son propre module admin. Pointe-le vers ton
panel Pelican comme pour n'importe quelle nouvelle install — rien de
spécifique à Peregrine côté orchestrateur. Peregrine écoute Pelican,
pas l'orchestrateur.

---

## Et si j'ai À LA FOIS un shop Stripe ET un orchestrateur ?

Setup supporté. Le lifecycle de chaque serveur est piloté par
exactement l'un des deux :

- Un serveur provisionné par le flow Stripe de Peregrine porte un
  `stripe_subscription_id` et un `server_configuration_id` — son
  lifecycle (suspend, terminate, refund) est piloté par les events
  Stripe.
- Un serveur provisionné par un orchestrateur n'a ni l'un ni l'autre
  — son lifecycle est piloté par l'orchestrateur (qui appelle l'API
  Application Pelican directement, et Pelican émet les events
  `eloquent.*` que Peregrine mirror).

Le receveur webhook Pelican vérifie les deux champs avant de
réagir, pour qu'aucun flow ne marche sur l'autre.

---

## Et le radio "Bridge mode" qu'on voyait avant ?

Supprimé. Le radio legacy `Disabled / ShopStripe / Paymenter` à
`/admin/bridge-settings` n'existe plus :

- Les webhooks Stripe sont câblés au moment où tu configures le
  secret dans `/admin/stripe-settings`.
- Les webhooks Pelican sont câblés au moment où tu les actives dans
  `/admin/pelican-webhook-settings`.
- Le multi-shop se câble dans `/admin/shops`.
- Chaque chose est indépendante. Aucun mode global à choisir.

Si tu utilisais Peregrine en mode `Paymenter` avant, la seule chose
à faire est de vérifier que ton webhook Pelican est activé — il
l'était déjà.

---

## Référence

- [Setup webhook receveur Pelican](/docs/pelican-webhook) —
  génération de token, modèle de sécurité, types d'événements.
- [Guide intégration multi-shop](/docs/shops) — pour le flow
  Stripe-driven en parallèle.
- [Spec Standard Webhooks](/docs/standard-webhooks) — s'applique aux
  webhooks SORTANTS de Peregrine (catalog sync vers les shops), pas
  au format de payload entrant Pelican.
