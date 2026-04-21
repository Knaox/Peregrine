# Shop integration guide

> **Audience** : dev qui travaille sur le Shop (biomebounty.com — SaaSykit / Laravel).
> **Intention** : donner la liste exhaustive de ce qui doit exister côté Shop pour que Peregrine (games.biomebounty.com) puisse déléguer l'auth et provisionner les serveurs après un paiement.

---

## Contexte

Peregrine et le Shop sont **deux projets indépendants**. Le Shop ne sait rien de Peregrine.

- **Le Shop** est l'Identity Provider OAuth2 (Laravel Passport) + vend les produits via Stripe.
- **Peregrine** se branche en OAuth2 client pour déléguer le login + écoute ses propres webhooks Stripe pour provisionner des serveurs Pelican.

Rien n'est partagé directement :

- Pas de DB commune — chacun sa base.
- Pas d'API Peregrine → Shop ni l'inverse.
- Stripe est le bus de communication indirect : il envoie les webhooks aux **deux** endpoints en parallèle.

Si Peregrine tombe, le Shop continue de vendre. Si le Shop tombe, Peregrine continue de fonctionner (les users déjà loggés gardent leur session, l'admin crée les nouveaux users à la main le temps que le Shop revienne).

---

## 1. OAuth2 server (Laravel Passport)

Peregrine délègue **l'authentification** au Shop. À chaque login, Peregrine redirige vers `/oauth/authorize` du Shop, attend un `code`, l'échange contre un `access_token`, puis fetch le profil.

### Install Passport

```bash
composer require laravel/passport
php artisan passport:install
php artisan passport:keys
```

Dans `config/auth.php`, le guard `api` utilise le driver `passport`. Passport publie les routes OAuth2 par défaut (`/oauth/authorize`, `/oauth/token`, …).

### Créer le client OAuth "Peregrine Panel"

```bash
php artisan passport:client
```

Réponses :

| Question | Valeur |
|---|---|
| Which user ID should the client be assigned to? | (laisser vide — client confidentiel) |
| What will you name the client? | `Peregrine Panel` |
| Where should we redirect the request after authorization? | `https://games.biomebounty.com/api/auth/social/shop/callback` |

Copier le **Client ID** + **Client Secret** retournés. À remettre à l'admin Peregrine qui les saisit dans `/admin/auth-settings` → section Shop.

### Endpoints exposés par Passport (aucun code à écrire)

| Endpoint | Rôle | Qui appelle |
|---|---|---|
| `GET /oauth/authorize` | Page de consentement — l'utilisateur se log et autorise Peregrine | Browser (redirigé par Peregrine) |
| `POST /oauth/token` | Échange le `code` reçu contre un `access_token` (Bearer) | Backend Peregrine (server-to-server) |

### Endpoint à implémenter : `GET /api/user`

Passport ne livre pas ça par défaut — c'est à toi de l'écrire. Contrat minimum :

```php
Route::middleware('auth:api')->get('/api/user', function (Request $request) {
    $u = $request->user();
    return response()->json([
        'id'    => $u->id,       // int ou string — unique stable, jamais recyclé
        'email' => $u->email,    // string — source de vérité de l'identité
        'name'  => $u->name,     // string — nom d'affichage
    ]);
});
```

**Ce qui compte :**

- Authentifié via `auth:api` (guard Passport) — Peregrine enverra `Authorization: Bearer {access_token}`.
- Les trois champs `id`, `email`, `name` sont obligatoires. Peregrine ignore tout le reste.
- L'`id` doit être stable pour la vie du user — si tu fais un data migration qui réassigne les IDs, tu casses le lien avec les comptes Peregrine existants.
- Pas besoin de renvoyer `email_verified` — Peregrine traite le Shop comme provider de confiance (provider canonique dans son modèle multi-provider OAuth).

### Scopes

Peregrine ne demande **aucun scope particulier** — scope vide. Si tu veux restreindre, tu peux, mais le endpoint `/api/user` doit rester accessible avec le scope vide.

### Ce que Peregrine attend côté config

L'admin Peregrine saisit dans `/admin/auth-settings → Shop` :

| Champ | Valeur à donner à l'admin |
|---|---|
| Client ID | retourné par `passport:client` |
| Client Secret | retourné par `passport:client` (le garder secret — chiffré côté Peregrine) |
| Authorize URL | `https://biomebounty.com/oauth/authorize` |
| Token URL | `https://biomebounty.com/oauth/token` |
| User profile URL | `https://biomebounty.com/api/user` |
| Redirect URI (lecture seule, info) | `https://games.biomebounty.com/api/auth/social/shop/callback` |

### Test du flow OAuth

Une fois tout branché, l'admin Peregrine va sur `/login` et voit le bouton "Sign in with Shop". Clic → redirige vers `/oauth/authorize` du Shop → user se log + autorise → redirige vers `/api/auth/social/shop/callback?code=...` → Peregrine échange le code → appelle `/api/user` → crée/retrouve le user local → session ouverte côté Peregrine.

---

## 2. Stripe webhooks

Chaque projet a son **propre endpoint webhook** avec sa **propre signing secret**. Stripe envoie chaque événement aux deux indépendamment.

### Côté Stripe Dashboard

Aller dans **Developers → Webhooks → Add endpoint**. Créer **deux** endpoints :

| Endpoint | URL | Secret |
|---|---|---|
| Shop | `https://biomebounty.com/webhook/stripe` | `STRIPE_WEBHOOK_SECRET` côté Shop |
| Peregrine | `https://games.biomebounty.com/webhook/stripe` | `STRIPE_WEBHOOK_SECRET` côté Peregrine (variable différente) |

Les deux endpoints doivent souscrire au moins aux événements suivants (plus pour le Shop selon ses besoins — invoicing, payment methods, etc.) :

- `checkout.session.completed`
- `customer.subscription.updated`
- `customer.subscription.deleted`

### Côté Shop

Tu continues de traiter les événements comme tu le fais aujourd'hui — sauvegarder les subscriptions, envoyer les factures, mettre à jour le statut du user, etc.

**Aucun code du Shop ne doit appeler Peregrine.** Peregrine reçoit son propre événement directement de Stripe.

### Côté Peregrine (pas ton boulot, pour info)

Peregrine a (aura — en cours de dev P3) un `StripeWebhookController` qui :

1. Vérifie la signature avec sa propre `STRIPE_WEBHOOK_SECRET` (Stripe la lui donne lors de la création du 2ᵉ endpoint).
2. Sur `checkout.session.completed` → dispatche un job qui provisionne le serveur Pelican.
3. Sur `customer.subscription.deleted` → suspend le serveur sur Pelican.
4. Idempotent via `payment_intent_id` — Stripe peut rejouer le webhook 10 fois, un seul serveur créé.

### Ce que le Shop doit garantir

- **Ne pas filtrer les événements destinés à Peregrine** — ils arrivent directement depuis Stripe, le Shop ne les voit même pas.
- **Les `metadata` du checkout session** sont partagées : si tu ajoutes `metadata.user_email` côté Shop (ex. pour un checkout guest), Peregrine le lira. Concerver la convention de tes `metadata` — Peregrine se cale sur `customer_email` et `line_items[0].price.id` par défaut.

---

## 3. Coordination products / prices

Peregrine maintient sa propre table `server_plans` qui mappe un `stripe_price_id` → specs Pelican (egg, RAM, CPU, disque, node).

### Workflow admin

1. **Côté Stripe** (ton boulot) : l'admin crée le product "Minecraft Starter" + un price récurrent (ex. `price_1ABCDEF`).
2. **Côté Shop** (ton boulot) : le product apparaît dans le catalogue, le user l'achète via le flow SaaSykit habituel.
3. **Côté Peregrine** (admin Peregrine, pas ton boulot) : l'admin ouvre `/admin/server-plans` → Create → colle `price_1ABCDEF` dans "Stripe Price ID" + choisit egg/RAM/CPU/disk/node.

Quand l'achat passe, `checkout.session.completed` arrive chez Peregrine avec ce `price_id`, le Bridge retrouve le `ServerPlan` correspondant et provisionne.

### Règle importante

**Si un `price_id` n'est pas mappé côté Peregrine**, le webhook renvoie une erreur (422) → Stripe retry → l'admin Peregrine voit l'échec dans Stripe Dashboard et crée le mapping manquant. Pas de serveur créé silencieusement dans le vide.

Convention conseillée : quand tu crées un nouveau product/price côté Stripe, prévenir l'admin Peregrine pour qu'il crée le `ServerPlan` mappé **avant** que le premier achat arrive.

---

## 4. Changement d'email user

Le Shop est la **source de vérité de l'email**.

- User change son email sur le Shop (page profil, vérif email, whatever — flow SaaSykit).
- La prochaine fois que ce user se log sur Peregrine via OAuth, Peregrine appelle `/api/user` qui renvoie le nouvel email.
- Peregrine détecte la différence avec sa DB locale → update sa propre DB + push vers Pelican via l'Application API.

**Tu n'as rien de spécial à coder pour ça côté Shop.** Juste renvoyer l'email à jour dans `/api/user`. La sync est déclenchée côté Peregrine au login.

**Tu n'as pas besoin d'appeler Peregrine** pour lui dire "l'email a changé" — il le découvre au login suivant. S'il faut forcer la sync plus tôt (rare), l'admin Peregrine peut invalider la session du user via `/admin/users`.

---

## 5. Checklist de dev

Étapes pour débloquer le login OAuth côté Peregrine :

- [ ] `composer require laravel/passport` + `passport:install` + `passport:keys`
- [ ] Guard `api` → driver `passport` dans `config/auth.php`
- [ ] `php artisan passport:client` avec la redirect URI `https://games.biomebounty.com/api/auth/social/shop/callback`
- [ ] Route `GET /api/user` derrière `auth:api` retournant `{id, email, name}`
- [ ] Communiquer `client_id` + `client_secret` à l'admin Peregrine (canal sécurisé — pas Slack public)

Étapes pour débloquer le Bridge auto-provisioning (quand Peregrine livre P3 côté panel) :

- [ ] Créer un 2ᵉ endpoint webhook Stripe pointant vers `https://games.biomebounty.com/webhook/stripe`
- [ ] Sélectionner les événements `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`
- [ ] Filer la signing secret du 2ᵉ endpoint à l'admin Peregrine (via `.env` côté Peregrine, jamais dans le code)
- [ ] Quand tu crées un nouveau product/price sur Stripe, prévenir l'admin Peregrine pour qu'il crée le `ServerPlan` mappé en amont

### À NE PAS faire

- ❌ Appeler Peregrine depuis le Shop pour "notifier d'une vente" — Stripe s'en charge via webhook
- ❌ Partager la DB — Peregrine a la sienne
- ❌ Appeler une API Peregrine pour créer un user — Peregrine crée ses users au premier login OAuth
- ❌ Filer un access_token long-terme — chaque login Peregrine redemande un `authorization_code` frais
- ❌ Utiliser le même `STRIPE_WEBHOOK_SECRET` que celui du Shop pour Peregrine — Stripe en donne un différent par endpoint, c'est exprès

---

## 6. Local dev

Pour tester le flow en local :

### Tunnel HTTPS

Peregrine attend une redirect URI en HTTPS publique (contrainte OAuth2). En local :

- Utiliser [ngrok](https://ngrok.com) ou [cloudflared](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/) pour exposer le Shop local : `ngrok http 8000` → `https://xxx.ngrok.io`.
- Recréer un client OAuth avec la redirect URI du Peregrine local (ex. `http://localhost:8001/api/auth/social/shop/callback`) — Passport accepte `http://localhost` même en "production".

### Stripe CLI

Pour forward les webhooks Stripe aux deux endpoints locaux :

```bash
# Session 1 — forward vers Shop local
stripe listen --forward-to localhost:8000/webhook/stripe

# Session 2 — forward vers Peregrine local
stripe listen --forward-to localhost:8001/webhook/stripe
```

Chaque `stripe listen` te donne une signing secret de test (`whsec_...`) — à mettre dans le `.env` du projet correspondant.

### Déclencher un webhook de test

```bash
stripe trigger checkout.session.completed
```

Les deux endpoints reçoivent l'événement en même temps. Vérifier côté Shop que la subscription est bien sauvegardée, et côté Peregrine que le serveur Pelican est bien provisionné (dès que P3 est livré).

---

## 7. Contacts

Quand c'est livré côté Shop :

- Admin Peregrine à prévenir : il doit saisir `client_id` + `client_secret` + les 3 URLs dans `/admin/auth-settings` → Shop, activer le toggle, tester un login OAuth depuis `/login` du panel
- Admin Stripe à prévenir : il doit copier la signing secret du 2ᵉ endpoint et la coller dans le `.env` de Peregrine (`STRIPE_WEBHOOK_SECRET`)

Questions sur le flow OAuth côté Peregrine : voir `docs/auth-architecture.md` (contributeurs only).
