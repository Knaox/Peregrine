# Peregrine

## Projet
Panel open source de gestion de serveurs de jeux via Pelican (fork de Pterodactyl).
- **Nom** : Peregrine (le titre affiché dans l'UI est configurable par l'admin dans les paramètres Filament)
- **Logo** : SVG fourni dans `public/images/logo.svg` — modifiable par l'admin via upload dans les paramètres
- **URL** : games.biomebounty.com
- **Shop associé** : biomebounty.com (SaaSykit, Laravel — projet SÉPARÉ, on n'y touche JAMAIS)
- **Stack** : Laravel 13 + Filament 5 (Livewire v4) + React 19 + TypeScript + Vite + Tailwind CSS + Scramble (doc API auto)
- **DB** : MySQL 8, base de données propre à Peregrine (séparée du Shop)
- **Queue** : Laravel Queue (Redis ou database driver)
- **Licence** : Open source — n'importe qui peut cloner et utiliser ce panel de manière standalone, sans le Shop BiomeBounty
- **Branding** : Le titre de l'application, le logo, et le favicon sont modifiables dans l'admin Filament (page "Paramètres > Apparence"). Stockés dans la table `settings` et cachés. Par défaut : "Peregrine" + logo faucon orange.

## Écosystème BiomeBounty

Deux produits INDÉPENDANTS qui communiquent via webhooks et OAuth :

### Shop (biomebounty.com)
- SaaSykit (Laravel) — on ne modifie PAS son code (sauf Laravel Passport installé)
- Gère : utilisateurs, produits/plans, paiements Stripe, abonnements, facturation
- Est le Identity Provider OAuth2 (Laravel Passport) pour Peregrine
- A sa propre DB

### Peregrine (games.biomebounty.com) — CE PROJET
- Laravel 13 + Filament 5 + React SPA
- Gère : serveurs de jeux, console, fichiers, stats, plugins
- Communique avec Pelican via son API
- Reçoit les webhooks Stripe directement (pas via le Shop)
- A sa propre DB séparée
- Titre, logo et favicon customisables par l'admin

### Communication entre les deux
- **Auth** : OAuth2 (Shop = provider, Peregrine = client) OU login local standalone
- **Paiements** : Stripe envoie les webhooks aux DEUX (Shop + Peregrine) indépendamment
- **Aucune dépendance directe** : si le Shop tombe, Peregrine continue. Si Peregrine tombe, le Shop continue de vendre.

## Architecture

### Authentification (dual mode, configurable)
- **Mode `local`** (défaut) : login email/password classique Laravel. Users créés localement (register, admin Filament, sync Pelican). Chaque user a un mot de passe hashé en DB.
- **Mode `oauth`** : OAuth2 Authorization Code flow. Le bouton "Se connecter" redirige vers le Shop (`OAUTH_AUTHORIZE_URL`). Le Shop authentifie (email + mdp + 2FA si activé), redirige vers Peregrine avec un code. Peregrine échange le code contre un access token, fetche le profil user, crée/met à jour le user en DB locale. Password NULL en mode OAuth.
- Le mode est défini par `AUTH_MODE=local|oauth` dans le `.env`
- **Sync email** : à chaque login OAuth, Peregrine compare l'email reçu du Shop avec la DB locale. Si différent → met à jour la DB locale ET Pelican via l'Application API. Le Shop est la source de vérité pour l'email.

### Gestion des utilisateurs (4 combinaisons possibles)
- **Sans Bridge, sans OAuth** (standalone) : users créés localement, login local, serveurs gérés manuellement par l'admin
- **Sans Bridge, avec OAuth** : users viennent du Shop via OAuth, serveurs gérés manuellement par l'admin
- **Avec Bridge + OAuth** (mode BiomeBounty) : webhook Stripe `checkout.session.completed` → Bridge vérifie si le user existe par email → si non : crée le user dans Peregrine DB + sur Pelican → provisionne le serveur. Login via OAuth.
- **Avec Bridge, sans OAuth** (hybride) : même flow Bridge, mais le user reçoit un email "définissez votre mot de passe" avec lien reset. Login local après.

### Pelican (deux API distinctes)
- **Application API** (`/api/application/`) : API admin. Crée/supprime users, provisionne serveurs, gère nodes/eggs/nests. Auth : API key admin (`PELICAN_ADMIN_API_KEY` dans le `.env`). Utilisée par le Bridge et l'admin Filament. JAMAIS exposée au frontend.
- **Client API** (`/api/client/`) : API utilisateur. Liste serveurs, console, fichiers, stats CPU/RAM, power control (start/stop/restart/kill). Auth : API key client (une par utilisateur Pelican). Proxifiée via le backend — le frontend React appelle Peregrine, Peregrine appelle Pelican. La clé client n'est JAMAIS exposée au navigateur.
- Les mots de passe Pelican sont SÉPARÉS du login principal. Section "Accès SFTP" dans Peregrine pour que le joueur définisse son mot de passe SFTP dédié.

### Performance & Stockage (DB-first + WebSocket pour le live)
- **Tout l'affichage vient de la DB locale** : eggs, nests, nodes, serveurs, users. Zéro appel API Pelican au chargement d'une page. La liste "Mes serveurs" = query SQL avec jointure sur eggs pour afficher le type de serveur.
- **Sync périodique** : job schedulé toutes les 5-10 min met à jour les statuts serveur en DB (running/stopped/offline) depuis l'API Pelican. Boutons "Sync Eggs", "Sync Nodes" dans l'admin Filament pour forcer la synchro des données de référence.
- **Cache Redis** : pour les settings/branding (TTL 1h) et les résultats de requêtes complexes (dashboard, stats). React : TanStack Query `staleTime` approprié.
- **WebSocket direct vers Wings** : console, logs, stats CPU/RAM/disk live. Flow : Peregrine génère un JWT via `GET /api/client/servers/{id}/websocket` → frontend connexion directe à Wings. Token refresh toutes les 10 min. Jamais en DB ni en cache.

### Bridge (module optionnel)
- Activable via `BRIDGE_ENABLED=true` dans le `.env`
- Écoute les webhooks Stripe directement (Peregrine a son propre endpoint webhook dans Stripe avec sa propre `STRIPE_WEBHOOK_SECRET`)
- Peregrine n'a PAS les clés Stripe complètes — uniquement la signing secret pour vérifier les webhooks
- Événements écoutés :
  - `checkout.session.completed` → créer le user si nécessaire + provisionner un nouveau serveur
  - `customer.subscription.updated` → upgrade/downgrade
  - `customer.subscription.deleted` → suspendre le serveur (uniquement serveurs AVEC abo)
- Table `server_plans` : mapping `stripe_price_id` → specs Pelican (egg_id, nest_id, ram, cpu, disk, node_id)
- Les données serveurs sont dans la table `servers` directement (user_id, pelican_server_id, statut, plan_id, stripe_subscription_id, payment_intent_id)
- DOIT être idempotent : vérifier `payment_intent_id` / `event_id` avant toute action
- Jobs Laravel avec queue + retry (3 tentatives) pour résilience
- Si désactivé, Peregrine fonctionne en standalone (gestion manuelle)

### Sync (pages admin Filament + commandes CLI)
La synchronisation avec Pelican se fait via l'admin Filament (boutons UI) ou via des commandes artisan (CLI).

**Page "Utilisateurs" (admin Filament) :**
- Tableau : liste tous les users Peregrine (email, nom, pelican_user_id, statut synchro)
- Bouton "Sync Users" → appelle `PelicanApplicationService->listUsers()`, compare avec la DB locale, affiche une modale :
  - Nouveaux users sur Pelican (pas encore dans Peregrine) → checkbox pour sélectionner lesquels importer
  - Users déjà synchro (match par email) → statut vert ✓
  - Users orphelins (dans Peregrine mais plus sur Pelican) → warning orange ⚠
- Bouton "Importer la sélection" → crée les users dans la DB Peregrine avec leur `pelican_user_id`
- Job async via queue pour les gros volumes
- **Bouton "Inviter sur le Shop"** (mode OAuth + Bridge) : envoie un email au user Pelican importé pour l'inviter à créer un compte Shop. Au premier login OAuth, Peregrine matche par email et lie les comptes automatiquement.
- **Matching manuel** : si emails différents entre Shop et Pelican, l'admin force le matching dans la page détail du user.

**Page "Serveurs" (admin Filament) :**
- Tableau : liste tous les serveurs Peregrine (nom, user, pelican_server_id, statut, plan, stripe_subscription_id)
- Bouton "Sync Serveurs" → appelle `PelicanApplicationService->listServers()`, compare avec la DB locale, affiche une modale :
  - Nouveaux serveurs sur Pelican → checkbox + dropdown pour rattacher à un user Peregrine
  - Serveurs déjà synchro → statut vert ✓
  - Serveurs orphelins → warning orange ⚠
- Bouton "Importer la sélection" → crée les serveurs dans la DB Peregrine avec mapping user + pelican_server_id

**Rattachement serveur ↔ abonnement Shop :**
- Les serveurs importés via sync n'ont PAS d'abo Stripe (`stripe_subscription_id` = NULL)
- Page détail serveur (admin Filament) : champ "Stripe Subscription ID" pour rattacher manuellement + dropdown "Server Plan" pour assigner un plan
- Serveur sans abo = fonctionne normalement, pas de suspension automatique. Serveur avec abo = suspendu automatiquement si l'abo expire.
- Peregrine ne CRÉE JAMAIS d'abo Stripe — c'est le Shop qui gère ça. Peregrine ne fait que recevoir et rattacher manuellement.

**ServerService :** Chaque serveur a un `ServerService` dédié qui encapsule toutes les opérations : start, stop, restart, suspend, unsuspend, delete, stats live, gestion fichiers. Le service utilise `PelicanClientService` en interne.

**Commandes artisan (alternatives CLI) :**
- `php artisan sync:users` — même logique que le bouton, mode interactif en CLI
- `php artisan sync:servers` — même logique
- `php artisan sync:eggs` — sync eggs & nests depuis Pelican
- `php artisan sync:nodes` — sync nodes depuis Pelican
- `php artisan sync:health` — health check quotidien schedulé, vérifie la cohérence des mappings

### Setup Wizard (première installation)
- Si `PANEL_INSTALLED` n'est pas `true`, tout le site redirige vers `/setup`
- SPA React autonome (pas Filament, pas Livewire)
- 7 étapes : Langue → DB (test connexion live) → Compte admin → Pelican URL + admin API key (test connexion live) → Auth mode (local ou OAuth2 + config) → Bridge (optionnel, Stripe webhook secret) → Récapitulatif + bouton Installer
- Le wizard écrit le `.env`, lance les migrations, crée le compte admin, set `PANEL_INSTALLED=true`
- Après installation, le wizard est inaccessible (middleware `EnsureInstalled`)
- Config modifiable après coup via page "Paramètres" dans l'admin Filament (écrit dans le `.env`)

### Plugins
- Architecture micro-frontend
- Chaque plugin expose un manifest JSON via `GET /api/plugins` : id, name, version, nav (label + icon + route), permissions requises, URL du bundle JS
- Peregrine lazy-load les bundles React à la demande
- Dépendances partagées via `window.PanelShared` (React, TanStack Query, react-i18next) pour éviter les doublons
- Chaque plugin est un mini-projet buildé séparément (Vite → IIFE bundle)
- Le Bridge est le premier plugin — même pattern pour tous les futurs plugins
- Les routes des plugins sont dynamiques : plugin désactivé = routes absentes = absent de la doc Scramble

### Documentation API
- Scramble auto-génère la doc OpenAPI 3.1.0 sur `/docs/api`
- Scanne les routes enregistrées dynamiquement — plugins activés/désactivés pris en compte automatiquement
- Pour que Scramble fonctionne bien : toujours typer les return types des controllers, utiliser Form Requests, utiliser API Resources

### Branding (customisable par l'admin)
- Le titre affiché dans l'UI ("Peregrine" par défaut) est modifiable dans l'admin Filament : Paramètres > Apparence
- Le logo (SVG/PNG) est uploadable depuis l'admin — stocké dans `storage/app/public/branding/`, servi via symlink `public/storage`
- Le favicon est aussi customisable
- Tout est stocké dans la table `settings` (key/value) et caché en mémoire via un `SettingsService` avec cache Laravel
- Le frontend React fetche le branding via `GET /api/settings/branding` (app_name, logo_url, favicon_url) au chargement
- Par défaut : titre "Peregrine", logo faucon orange, favicon dérivé du logo
- Un hébergeur qui utilise Peregrine pour son propre service peut donc renommer et rebrander entièrement sans toucher au code

### Docker (mode d'installation recommandé)
- `docker compose up -d` lance tout l'environnement : PHP-FPM 8.3, Nginx, MySQL 8, Redis
- Deux modes :
  - **Développement** : volumes montés, hot-reload Vite, xdebug, logs visibles
  - **Production** : images optimisées, assets pré-buildés, pas de devtools
- Le `docker-compose.yml` est à la racine du projet
- `Dockerfile` multi-stage : stage 1 = build frontend (pnpm + Vite), stage 2 = PHP-FPM avec les assets buildés
- Le Setup Wizard détecte s'il tourne dans Docker (via variable `DOCKER=true`) et pré-remplit la config DB automatiquement (host=mysql, port=3306, etc.)
- Variables Docker exposées dans `.env` : `DOCKER=true|false`, `DOCKER_APP_PORT=8080`, `DOCKER_DB_PORT=3306`
- Installation Docker : `git clone` → `docker compose up -d` → ouvrir le navigateur → Setup Wizard
- Installation classique (sans Docker) : `git clone` → `composer install` → `pnpm install` → config manuelle → Setup Wizard
- Les deux modes d'installation mènent au même Setup Wizard

## Base de données

### Tables principales
- `users` : id, email, name, password (nullable si OAuth ou Bridge auto-create), locale (enum en/fr, default en), pelican_user_id (nullable), stripe_customer_id (nullable, pour matching webhooks Bridge), oauth_provider (nullable), oauth_id (nullable), timestamps
- `servers` : id, user_id (FK), pelican_server_id, name, status (enum: active/suspended/terminated/running/stopped/offline), egg_id (FK), plan_id (nullable FK), stripe_subscription_id (nullable), payment_intent_id (nullable, pour idempotence Bridge), timestamps
- `eggs` : id, pelican_egg_id, nest_id (FK), name, docker_image, startup (text), description (nullable), timestamps
- `nests` : id, pelican_nest_id, name, description (nullable), timestamps
- `nodes` : id, pelican_node_id, name, fqdn, memory (int), disk (int), location (string nullable), timestamps
- `server_plans` : id, name, stripe_price_id, egg_id (FK), nest_id (FK), ram, cpu, disk, node_id (FK), is_active (boolean), timestamps
- `settings` : id, key (unique string), value (text nullable), timestamps. Stocke le branding (app_name, app_logo_path, app_favicon_path) et toute config modifiable via l'admin. Cachée en mémoire.
- `sync_logs` : id, type (enum: users/servers/eggs/nodes/health), status (enum: running/completed/failed), summary (json nullable), started_at, completed_at, timestamps

### Principes
- Peregrine a sa propre DB, JAMAIS de lecture/écriture dans la DB du Shop
- Les eggs, nests et nodes sont dupliqués en DB locale depuis Pelican (synchro via bouton ou job schedulé). La DB locale est utilisée pour l'affichage, Pelican reste la source de vérité — la sync met à jour la copie locale.
- La liste des serveurs, les détails d'un serveur, les eggs = tout depuis la DB locale. Les stats live (CPU, RAM, console) = WebSocket direct vers Wings.

## Configuration (.env)

Toutes les valeurs sont écrites automatiquement par le Setup Wizard. Édition manuelle non requise.

```env
PANEL_INSTALLED=true|false
APP_URL=https://games.biomebounty.com

# Base de données
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# Authentification
AUTH_MODE=local|oauth
OAUTH_CLIENT_ID=
OAUTH_CLIENT_SECRET=
OAUTH_AUTHORIZE_URL=
OAUTH_TOKEN_URL=
OAUTH_USER_URL=

# Pelican
PELICAN_URL=
PELICAN_ADMIN_API_KEY=

# Bridge (optionnel)
BRIDGE_ENABLED=false
STRIPE_WEBHOOK_SECRET=

# Docker (auto-détecté)
DOCKER=true|false
DOCKER_APP_PORT=8080
DOCKER_DB_PORT=3306
```

## Règles de codage

### i18n (Internationalisation)
- Langues supportées : EN (défaut) + FR. Toute nouvelle langue sera ajoutée plus tard via le même système.
- JAMAIS de texte en dur dans l'UI — tout passe par des clés i18n, sans exception.
- Frontend React : `react-i18next` avec des fichiers JSON par langue dans `resources/js/i18n/` (`en.json`, `fr.json`).
- Les clés sont organisées par namespace/section : `"servers.list.title"`, `"setup.steps.database"`, `"auth.login.button"`, etc.
- Les deux fichiers EN et FR doivent TOUJOURS être mis à jour en même temps. Si tu ajoutes une clé dans `en.json`, tu ajoutes la traduction FR dans `fr.json` dans le même commit.
- Backend Laravel : fichiers de langue dans `lang/en/` et `lang/fr/` pour les messages serveur (emails, notifications, erreurs API, validation).
- La langue est détectée automatiquement via le header `Accept-Language` du navigateur, avec fallback sur EN.
- L'utilisateur peut changer sa langue dans les paramètres de son profil (stockée en DB, column `locale` sur la table `users`).
- Le Setup Wizard demande la langue en première étape et l'utilise pour tout le wizard.
- Convention de nommage des clés : snake_case, en anglais, hiérarchique avec des points. Ex : `servers.status.active`, `bridge.sync.success`, `plugins.not_found`.
- Les messages d'erreur API retournent des clés i18n (ex: `{ "error": "servers.not_found" }`), le frontend traduit côté client.
- Pluralisation : utiliser les fonctions natives de i18next (`{{count}}`) et de Laravel (`trans_choice`).

### Générales
- TypeScript strict, JAMAIS de `any` — utiliser `unknown` + type guards si nécessaire
- Fichiers : maximum 300 lignes. Si ça dépasse, découper.
- Chaque composant React dans son propre fichier
- Chaque props type/interface dans son propre fichier (ex: `ServerCard.props.ts`)
- Chaque hook custom dans son propre fichier dans `hooks/`
- Chaque service dans son propre fichier dans `services/`
- Nommage : PascalCase pour composants/types, camelCase pour fonctions/variables, UPPER_SNAKE_CASE pour constantes
- Pas d'emoji dans l'UI
- Imports : absolus avec alias `@/` (pas de `../../../`)
- Pas de `console.log` en production — utiliser un logger dédié
- Pas de `eslint-disable` sans justification en commentaire
- Pas de `@ts-ignore` — jamais

### React
- Composants fonctionnels uniquement (pas de classes)
- Props typées avec interface dédiée (pas inline)
- Un composant = un fichier = une responsabilité
- Hooks : extraire toute logique complexe dans un hook custom
- État global : Zustand ou Context selon la portée
- Data fetching : TanStack Query avec `staleTime` approprié
- Animations : CSS uniquement (transitions/keyframes), pas de librairie d'animation
- Lazy loading pour les routes et les plugins

### Laravel / Backend
- Controllers : thin controllers, logique dans les Services
- Chaque Service dans `app/Services/`
- Form Requests pour la validation (pas de validation dans le controller)
- Resources pour les réponses API (pas de `->toArray()` direct)
- Typer les return types des controllers (Scramble les utilise pour auto-générer la doc API)
- Policies pour les autorisations
- Events + Listeners pour le découplage (pas d'appels directs entre modules)
- Migrations : une migration = une table ou une modification
- Seeders pour les données de test
- Tests : Feature tests pour les endpoints, Unit tests pour les services
- Queues : tout job long (appel API Pelican, envoi email) passe par la queue

### Sécurité
- Jamais de secret/clé dans le code — tout en `.env`
- Webhook Stripe : toujours vérifier la signature
- API Pelican : clé admin jamais exposée au frontend
- CSRF activé sur toutes les routes web
- Rate limiting sur les endpoints sensibles
- Idempotence sur tous les handlers de webhook

### Git
- Pas de commit automatique — ATTENDS MA VALIDATION avant de commit
- Pas de version bump sauf si demandé explicitement
- Messages de commit en anglais, conventionnels (feat:, fix:, refactor:, etc.)
- Un commit = un changement logique

## Structure du projet
```
peregrine/
├── CLAUDE.md
├── README.md
├── Dockerfile                 # Multi-stage: build frontend + PHP-FPM
├── docker-compose.yml         # Dev: PHP-FPM + Nginx + MySQL + Redis
├── docker-compose.prod.yml    # Production overrides
├── docker/
│   ├── nginx/
│   │   └── default.conf       # Config Nginx
│   ├── php/
│   │   └── php.ini            # Config PHP custom
│   └── mysql/
│       └── init.sql           # Script init DB (create database si nécessaire)
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/           # API endpoints pour le React SPA
│   │   │   ├── Setup/         # Setup Wizard API (test DB, test Pelican, install)
│   │   │   └── Webhook/       # Webhook handlers (Stripe)
│   │   ├── Middleware/
│   │   │   └── EnsureInstalled.php  # Redirige vers /setup si pas installé
│   │   ├── Requests/          # Form Requests
│   │   └── Resources/         # API Resources
│   ├── Models/
│   ├── Services/              # Business logic
│   │   ├── Pelican/
│   │   │   ├── PelicanApplicationService.php
│   │   │   └── PelicanClientService.php
│   │   ├── ServerService.php  # Opérations par serveur (start/stop/restart/suspend/files/stats)
│   │   ├── SyncService.php    # Logique de sync users + serveurs (compare Pelican vs DB locale)
│   │   └── SettingsService.php # Lecture/écriture settings + cache
│   ├── Jobs/                  # Queue jobs
│   ├── Events/
│   ├── Listeners/
│   ├── Policies/
│   └── Plugins/               # Plugin system core
│       └── Bridge/            # Bridge module
│           ├── BridgeServiceProvider.php
│           ├── Config/
│           ├── Migrations/
│           ├── Models/
│           ├── Services/
│           ├── Jobs/
│           └── Listeners/
├── config/
│   ├── panel.php              # Config générale du panel
│   ├── auth-mode.php          # Config auth (local/oauth)
│   └── bridge.php             # Config Bridge (si activé)
├── lang/
│   ├── en/                    # Traductions backend EN
│   │   ├── auth.php
│   │   ├── servers.php
│   │   ├── bridge.php
│   │   └── validation.php
│   └── fr/                    # Traductions backend FR
│       ├── auth.php
│       ├── servers.php
│       ├── bridge.php
│       └── validation.php
├── database/
│   └── migrations/
├── resources/
│   └── js/                    # React SPA
│       ├── app.tsx
│       ├── components/
│       ├── hooks/
│       ├── pages/
│       ├── services/
│       ├── stores/
│       ├── types/
│       ├── plugins/           # Plugin loader
│       ├── setup/             # Setup Wizard SPA (première installation)
│       │   ├── SetupWizard.tsx
│       │   ├── steps/
│       │   │   ├── LanguageStep.tsx
│       │   │   ├── DatabaseStep.tsx
│       │   │   ├── AdminStep.tsx
│       │   │   ├── PelicanStep.tsx
│       │   │   ├── AuthStep.tsx
│       │   │   ├── BridgeStep.tsx
│       │   │   └── SummaryStep.tsx
│       │   ├── components/
│       │   └── hooks/
│       └── i18n/
│           ├── en.json        # Traductions frontend EN (source of truth)
│           └── fr.json        # Traductions frontend FR (toujours synchro avec en.json)
├── public/
│   └── images/
│       ├── logo.svg           # Logo par défaut (Peregrine faucon orange)
│       └── favicon.svg        # Favicon par défaut
├── routes/
│   ├── api.php
│   └── web.php
├── tests/
│   ├── Feature/
│   └── Unit/
└── .env.example
```

## Commandes utiles

```bash
# Docker
docker compose up -d              # Lancer l'environnement complet
docker compose down               # Arrêter l'environnement
docker compose logs -f app        # Voir les logs PHP
docker compose exec app bash      # Shell dans le container PHP

# Setup & Installation (sans Docker)
composer install                  # Installer les dépendances PHP
pnpm install                       # Installer les dépendances JS
pnpm run dev                       # Lancer Vite en mode dev
pnpm run build                     # Build production

# Base de données
php artisan migrate               # Lancer les migrations
php artisan migrate:fresh --seed  # Reset DB + seed

# Sync Pelican
php artisan sync:users            # Sync interactif des users Pelican
php artisan sync:servers          # Sync interactif des serveurs Pelican
php artisan sync:eggs             # Sync eggs & nests depuis Pelican
php artisan sync:nodes            # Sync nodes depuis Pelican
php artisan sync:health           # Health check des mappings

# Queue
php artisan queue:work            # Worker pour les jobs async

# Documentation API
# Accessible sur /docs/api (auto-généré par Scramble)
```
