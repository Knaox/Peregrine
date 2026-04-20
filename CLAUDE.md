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

#### Plugin mail / jobs — contrat queue-safe
- **Aucune classe de plugin (Mailable, Job, Event) ne doit JAMAIS être sérialisée dans la queue.** Les payloads figés dans la table `jobs` survivent aux changements de code et finissent en `__PHP_Incomplete_Class` sur unserialize.
- Pour dispatcher un mail depuis un plugin : `App\Jobs\SendPluginMail::dispatch($email, Plugins\Foo\Mail\BarMail::class, [...scalaires...])`. Le Mailable est reconstruit au `handle()` — seuls des primitives voyagent dans la queue.
- Le Mailable d'un plugin **ne doit PAS** implémenter `ShouldQueue`. Seul `SendPluginMail` (core) l'implémente. Marquer la classe `final` pour empêcher la régression.
- Après activation/désactivation d'un plugin, `PluginManager` lance automatiquement `queue:restart` (workers daemon relèvent leur autoload map) et purge les jobs orphelins référençant le namespace du plugin.
- En cas de résidus après un refactor incompatible : `php artisan plugin:purge-stale-jobs {plugin_id}` nettoie `jobs` + `failed_jobs`.

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
PELICAN_CLIENT_API_KEY=

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

## Filament v5 Reference

Filament v5 has significant namespace changes compared to v3/v4. Here are the correct namespaces verified against the actual vendor directory.

### Namespace Changes (v3/v4 -> v5)

**Actions (MAJOR CHANGE):**
All table actions moved from `Filament\Tables\Actions\*` to `Filament\Actions\*`:
- `Filament\Actions\EditAction`
- `Filament\Actions\DeleteAction`
- `Filament\Actions\ViewAction`
- `Filament\Actions\CreateAction`
- `Filament\Actions\BulkActionGroup`
- `Filament\Actions\DeleteBulkAction`
- `Filament\Actions\Action` (generic action, also used for form submit buttons)

**Form Components (mostly unchanged):**
Form field components remain in `Filament\Forms\Components\*`:
- `Filament\Forms\Components\TextInput`
- `Filament\Forms\Components\Select`
- `Filament\Forms\Components\Toggle`
- `Filament\Forms\Components\Radio`
- `Filament\Forms\Components\Textarea`
- `Filament\Forms\Components\Checkbox`
- `Filament\Forms\Components\DatePicker`
- `Filament\Forms\Components\FileUpload`
- `Filament\Forms\Components\Repeater`

**Layout Components (MOVED to Schemas):**
Layout/structural components moved from `Filament\Forms\Components\*` to `Filament\Schemas\Components\*`:
- `Filament\Schemas\Components\Section` (was `Filament\Forms\Components\Section`)
- `Filament\Schemas\Components\Grid` (was `Filament\Forms\Components\Grid`)
- `Filament\Schemas\Components\Tabs` (was `Filament\Forms\Components\Tabs`)
- `Filament\Schemas\Components\Fieldset` (was `Filament\Forms\Components\Fieldset`)
- `Filament\Schemas\Components\Wizard` (was `Filament\Forms\Components\Wizard`)

**Schema (replaces Form in resource signatures):**
- `Filament\Schemas\Schema` replaces `Filament\Forms\Form` in `Resource::form()` method signature

**Utility Classes (MOVED):**
- `Filament\Schemas\Components\Utilities\Get` (was `Filament\Forms\Get`)

**Table Columns (unchanged):**
- `Filament\Tables\Columns\TextColumn`
- `Filament\Tables\Columns\IconColumn`
- `Filament\Tables\Columns\BadgeColumn`
- `Filament\Tables\Columns\BooleanColumn`
- `Filament\Tables\Columns\ToggleColumn`
- `Filament\Tables\Columns\ImageColumn`

**Table Filters (unchanged):**
- `Filament\Tables\Filters\TernaryFilter`
- `Filament\Tables\Filters\SelectFilter`

**Widgets (unchanged):**
- `Filament\Widgets\StatsOverviewWidget`
- `Filament\Widgets\StatsOverviewWidget\Stat`
- `Filament\Widgets\TableWidget`
- `Filament\Widgets\ChartWidget`

**Pages (unchanged):**
- `Filament\Resources\Pages\ListRecords`
- `Filament\Resources\Pages\CreateRecord`
- `Filament\Resources\Pages\EditRecord`
- `Filament\Pages\Page`
- `Filament\Pages\Dashboard`

**Other (unchanged):**
- `Filament\Resources\Resource`
- `Filament\PanelProvider`
- `Filament\Panel`
- `Filament\Tables\Table`
- `Filament\Forms\Concerns\InteractsWithForms`
- `Filament\Forms\Contracts\HasForms`

### Resource Method Signatures (v5)

```php
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MyResource extends Resource
{
    // form() now takes Schema, not Form
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([...]);
    }

    // table() is unchanged
    public static function table(Table $table): Table
    {
        return $table->columns([...])->filters([...])->actions([...])->bulkActions([...]);
    }
}
```

### Deprecated Methods in v5

- `Table::bulkActions()` is deprecated, use `Table::toolbarActions()` instead
- `Table::actions()` is an alias for `Table::recordActions()` (both work)

### Common Gotchas vs v3/v4

1. **Section moved**: `Forms\Components\Section` does NOT exist. Use `Schemas\Components\Section`.
2. **Actions moved**: `Tables\Actions\EditAction` etc. do NOT exist. Use `Filament\Actions\EditAction`.
3. **Form -> Schema**: The `form()` method signature changed from `Form $form` to `Schema $schema`.
4. **Get utility moved**: `Forms\Get` does NOT exist. Use `Schemas\Components\Utilities\Get`.
5. **Form inputs stayed**: `TextInput`, `Select`, `Toggle`, `Radio` etc. are still in `Forms\Components\*`, NOT in `Schemas\Components\*`. Only layout components (Section, Grid, Tabs, Fieldset) moved to Schemas.
6. **Form submit actions**: Use `Filament\Actions\Action` for form actions (e.g., save buttons), not a separate form action class.

## État du projet

### Fait (résumé)
**Panel joueur complet** : Laravel 13 + Filament 5 + React 19 + TS + Vite + Tailwind. Docker. Setup Wizard (7 étapes). Auth dual mode (local+OAuth). Filament admin (7 resources, widgets, Settings, Theme).

**8 pages serveur fonctionnelles** : Overview (WebSocket stats live), Console (WebSocket + power controls + état serveur), Files (drag-drop upload, bulk select/delete/compress, éditeur), SFTP (credentials + quick connect clipboard), Databases (CRUD + rotate password), Backups (create/download/restore/lock/delete), Schedules (presets + tasks + cron avancé), Network (allocations + notes + primary + bulk delete + add).

**Système de thème complet** : 15+ couleurs personnalisables via admin Filament, card style/sort/group/columns, sidebar configurable (position left/top, style default/compact/pills, entries drag-and-drop), toutes les options fonctionnelles. Thème crimson par défaut. 0 couleur hardcodée.

**Header personnalisable** : liens custom avec icônes, labels multi-langue (EN/FR), toggle ouverture nouvel onglet, taille logo configurable, toggle affichage nom app.

**Cache Redis** : allocations (10min), SFTP details (30min), theme (1h), branding (1h), databases/backups/schedules/network (2-10min). Prefetch on hover des ServerCards. Splash screen instantané.

**i18n FR/EN complet** avec accents corrects. Routes serveur dynamiques depuis la config sidebar. Uptime serveur via API Pelican. IP:port via allocation alias (fallback IP réelle).

**Services Pelican séparés** : PelicanClientService, PelicanFileService, PelicanDatabaseService, PelicanBackupService, PelicanScheduleService, PelicanNetworkService + trait MakesClientRequests.

Git repo : https://github.com/Knaox/Peregrine

### Pas fait
- [ ] **P2** — Plugin slots : zones d'injection pour les plugins (sidebar entries, overview widgets, detail sections)
- [ ] **P2** — UI sync avancée Filament (modales comparaison avec checkboxes, Inviter sur le Shop, matching manuel email)
- [ ] **P3** — Bridge plugin (BridgeServiceProvider, StripeWebhookController, ProvisioningService, SubscriptionService, jobs queue, idempotence)
- [ ] **P4** — Auth features (forgot/reset password endpoints + pages React, email verification)
- [ ] **P4** — Templates email (serveur prêt, invitation Shop, reset password) — i18n EN+FR
- [ ] **P4** — Plugin system : GET /api/plugins manifest, PluginLoader React, window.PanelShared
- [ ] **P5** — Tests (Feature tests API, Unit tests services)
- [ ] **P5** — Infra & DX (README.md, CI/CD GitHub Actions)

## Roadmap détaillée

### Priorité 2 — Système de customisation complet (thème + layout + composants)

L'admin doit pouvoir personnaliser ENTIÈREMENT l'apparence et le comportement du panel sans toucher au code. Ce n'est pas juste un color picker — c'est un système de configuration visuelle complet qui contrôle : les couleurs, la typographie, les formes, la disposition, le contenu des cards, la sidebar, les icônes, et l'admin Filament.

#### 2.1 — Thème visuel (couleurs, fonts, formes)

**DÉJÀ FAIT :** ThemeService basique (getTheme, getThemeCssVariables), endpoint `GET /api/settings/theme`, ThemeProvider React, 16 clés `theme_*` en DB, CSS variables dans theme.css, migration des 8 composants UI + AppLayout vers CSS vars.

**RESTE À FAIRE :** Pages Filament pour éditer le thème (color pickers, font selector, radius, CSS custom), `getLayoutConfig()` dans ThemeService, intégration des clés `layout_*`/`card_*`/`sidebar_*`.

**Clés settings thème visuel :**
- `theme_mode` : `dark` (défaut), `light`, `auto`
- `theme_primary` : `#f97316` (orange)
- `theme_accent` : `#3b82f6` (blue)
- `theme_danger` : `#ef4444` (red)
- `theme_warning` : `#f59e0b` (amber)
- `theme_success` : `#22c55e` (green)
- `theme_background` : `#0f172a` (slate-900)
- `theme_surface` : `#1e293b` (slate-800)
- `theme_surface_hover` : `#334155` (slate-700)
- `theme_border` : `#334155` (slate-700)
- `theme_text_primary` : `#f8fafc` (slate-50)
- `theme_text_secondary` : `#94a3b8` (slate-400)
- `theme_text_muted` : `#64748b` (slate-500)
- `theme_radius` : `lg` (none/sm/md/lg/xl/2xl/full)
- `theme_font` : `inter` (inter/plus-jakarta/space-grotesk/outfit/system)
- `theme_custom_css` : `` (vide — CSS custom injecté dans un `<style>`)

#### 2.2 — Customisation des Server Cards (liste serveurs)

L'admin configure ce qui s'affiche dans chaque carte serveur de la liste.

**Clé settings :** `card_server_config` — JSON :
```json
{
  "layout": "grid",
  "columns": { "desktop": 3, "tablet": 2, "mobile": 1 },
  "show_egg_icon": true,
  "show_egg_name": true,
  "show_plan_name": true,
  "show_status_badge": true,
  "show_stats_bars": true,
  "show_quick_actions": true,
  "show_ip_port": false,
  "show_uptime": false,
  "show_player_count": false,
  "stats_bars": ["cpu", "ram", "disk"],
  "card_style": "default",
  "sort_default": "name",
  "group_by": "none"
}
```

**Options `card_style`** : `default` (bordure simple), `elevated` (shadow), `glass` (glassmorphism avec backdrop-blur), `minimal` (pas de bordure, juste le contenu)

**Options `group_by`** : `none`, `egg`, `nest`, `status`, `plan`

**Options `sort_default`** : `name`, `status`, `created_at`, `egg`

**Page Filament** "Paramètres > Cards Serveurs" :
- Preview live de la carte avec les options activées (miniature dans le formulaire)
- Toggles pour chaque élément (egg icon, egg name, plan, stats, etc.)
- Dropdown pour le style de carte
- Dropdown pour le groupement et le tri par défaut
- Sliders pour le nombre de colonnes par breakpoint

#### 2.3 — Customisation de la Sidebar serveur

L'admin configure les entrées de la sidebar dans la page détail serveur. Il peut activer/désactiver des entrées, changer l'ordre, changer les icônes, et les plugins ajoutent les leurs.

**Clé settings :** `sidebar_server_config` — JSON :
```json
{
  "position": "left",
  "style": "default",
  "show_server_status": true,
  "show_server_name": true,
  "entries": [
    { "id": "overview", "label_key": "servers.detail.overview", "icon": "home", "enabled": true, "route_suffix": "", "order": 0 },
    { "id": "console", "label_key": "servers.detail.console", "icon": "terminal", "enabled": true, "route_suffix": "/console", "order": 1 },
    { "id": "files", "label_key": "servers.detail.files", "icon": "folder", "enabled": true, "route_suffix": "/files", "order": 2 },
    { "id": "databases", "label_key": "servers.detail.databases", "icon": "database", "enabled": false, "route_suffix": "/databases", "order": 3 },
    { "id": "backups", "label_key": "servers.detail.backups", "icon": "archive", "enabled": false, "route_suffix": "/backups", "order": 4 },
    { "id": "schedules", "label_key": "servers.detail.schedules", "icon": "clock", "enabled": false, "route_suffix": "/schedules", "order": 5 },
    { "id": "network", "label_key": "servers.detail.network", "icon": "globe", "enabled": false, "route_suffix": "/network", "order": 6 },
    { "id": "sftp", "label_key": "servers.detail.sftp", "icon": "key", "enabled": true, "route_suffix": "/sftp", "order": 7 }
  ]
}
```

**Options `position`** : `left` (défaut), `top` (barre d'onglets horizontale en haut)

**Options `style`** : `default` (sidebar pleine), `compact` (icônes seules, labels au hover), `pills` (boutons arrondis)

**Icônes disponibles** : l'admin choisit dans une liste d'icônes prédéfinies (home, terminal, folder, database, archive, clock, globe, key, settings, shield, users, server, plus, link, code, cpu, hard-drive, etc.). Stockées comme identifiant string, le frontend mappe vers les SVG inline correspondants.

**Page Filament** "Paramètres > Sidebar serveur" :
- Drag-and-drop pour réordonner les entrées
- Toggle on/off par entrée
- Dropdown icône par entrée (avec preview)
- Select position (left/top) et style (default/compact/pills)
- Les plugins ajoutent leurs entrées automatiquement (via le manifest), l'admin peut les activer/désactiver et les réordonner

#### 2.4 — Customisation des Widgets (page détail serveur)

L'admin configure les widgets affichés dans la vue Overview du serveur.

**Clé settings :** `widgets_server_config` — JSON :
```json
{
  "widgets": [
    { "id": "stats", "enabled": true, "order": 0, "config": { "show_cpu": true, "show_ram": true, "show_disk": true, "show_network": true } },
    { "id": "info", "enabled": true, "order": 1, "config": {} },
    { "id": "activity", "enabled": false, "order": 2, "config": { "limit": 10 } },
    { "id": "quick_actions", "enabled": false, "order": 3, "config": { "actions": [] } }
  ]
}
```

**Widgets built-in** :
- `stats` — cartes CPU/RAM/Disk/Network (activables individuellement)
- `info` — infos serveur (egg, plan, date création)
- `activity` — log des 10 dernières actions (start, stop, file edit, etc.)
- `quick_actions` — boutons personnalisables (liens Shop, Discord, wiki — l'admin définit label + URL + icône)

**Les plugins enregistrent leurs propres widgets** via le manifest JSON. L'admin peut les activer/ordonner comme les built-in.

**Page Filament** "Paramètres > Widgets serveur" :
- Drag-and-drop pour réordonner
- Toggle on/off par widget
- Options par widget (dépliable)
- Pour `quick_actions` : repeater Filament pour définir les liens (label, url, icon, new_tab)

#### 2.5 — Intégration Filament (admin panel)

Le thème s'applique AUSSI à l'admin Filament. L'`AdminPanelProvider` lit les couleurs depuis `ThemeService` :

```php
// AdminPanelProvider.php
->colors([
    'primary' => Color::hex($themeService->get('theme_primary', '#f97316')),
    'danger' => Color::hex($themeService->get('theme_danger', '#ef4444')),
    'warning' => Color::hex($themeService->get('theme_warning', '#f59e0b')),
    'success' => Color::hex($themeService->get('theme_success', '#22c55e')),
])
```

L'admin Filament suit la couleur primary choisie. Le logo et le branding sont déjà gérés par la page Settings existante.

#### 2.6 — Frontend : architecture

**DÉJÀ FAIT :** `ThemeProvider` (fetch + inject CSS vars + Google Font), `useTheme.ts`.

**RESTE À FAIRE :**

**`useCardConfig()`** — hook qui lit `card_server_config` depuis le thème, utilisé par `ServerCard` pour conditionner l'affichage (show/hide egg, stats, etc.)

**`useSidebarConfig()`** — hook qui lit `sidebar_server_config`, utilisé par `ServerSidebar` pour rendre les entrées dans l'ordre avec les bonnes icônes

**`useWidgetConfig()`** — hook qui lit `widgets_server_config`, utilisé par `ServerOverviewPage` pour rendre les widgets dans l'ordre

**`IconMap`** (`resources/js/utils/icons.tsx`) — mapping identifiant string → composant SVG inline. Utilisé par la sidebar et les widgets pour rendre l'icône choisie par l'admin.

**RÈGLE CRITIQUE** : AUCUNE couleur Tailwind en dur dans les composants. Tout passe par les CSS variables :
- `bg-slate-900` → `bg-[var(--color-background)]`
- `bg-slate-800` → `bg-[var(--color-surface)]`
- `border-slate-700` → `border-[var(--color-border)]`
- `text-white` → `text-[var(--color-text-primary)]`
- `text-slate-400` → `text-[var(--color-text-secondary)]`
- `bg-orange-500` → `bg-[var(--color-primary)]`
- `rounded-lg` → `rounded-[var(--radius)]`

Il faudra migrer TOUS les composants existants pour utiliser les CSS variables.

#### 2.7 — Pages Filament pour la customisation

4 pages dans l'admin Filament sous le groupe "Apparence" :

1. **Paramètres > Thème** — couleurs, font, radius, mode, CSS custom
2. **Paramètres > Cards serveurs** — contenu des cartes, style, colonnes, groupement
3. **Paramètres > Sidebar serveur** — entrées, ordre, icônes, position, style
4. **Paramètres > Widgets serveur** — widgets, ordre, options, quick actions

Chaque page lit/écrit dans la table `settings` via `SettingsService`, et le cache est invalidé au save. Le frontend re-fetch le thème au prochain chargement (TanStack Query staleTime 1h, mais un bouton "Prévisualiser" dans l'admin force un refresh immédiat).

### Priorité 2 — UI sync avancée Filament

**DÉJÀ FAIT :** 5 commandes artisan (sync:users/servers/eggs/nodes/health), SyncServerStatusJob (5 min), boutons sync basiques dans Filament (import automatique + notifications).

**RESTE À FAIRE :** UI sync avancée dans Filament

**Page Users** — ajouter à `UserResource` :
- Header action "Sync Users" → ouvre une modale Livewire qui appelle `SyncService->compareUsers()`, affiche le résultat en 3 sections (nouveaux avec checkboxes, existants en vert, orphelins en orange), bouton "Importer la sélection"
- Header action "Inviter sur le Shop" (visible uniquement si `config('auth-mode.mode') === 'oauth' && config('bridge.enabled')`) → envoie un email d'invitation via `Mail::to()` avec un template Blade
- Dans la fiche Edit user : champ email modifiable (matching manuel)

**Page Servers** — ajouter à `ServerResource` :
- Header action "Sync Serveurs" → même pattern modale, avec un dropdown Select pour rattacher chaque nouveau serveur à un user Peregrine existant
- Dans la fiche Edit serveur : champ `stripe_subscription_id` (text, nullable), dropdown `plan_id` (relation select)

**Pages Eggs et Nodes** — ajouter :
- Header action "Sync Eggs" / "Sync Nodes" → appelle directement `SyncService->syncEggs()` / `syncNodes()`, affiche notification de succès

### Priorité 3 — Bridge plugin

Module optionnel qui automatise le provisioning de serveurs quand un client achète via Stripe.

#### Structure

```
app/Plugins/Bridge/
├── BridgeServiceProvider.php    # Enregistrement conditionnel
├── Config/bridge.php            # Config (déjà existe dans config/)
├── Http/
│   ├── Controllers/
│   │   └── StripeWebhookController.php
│   └── Middleware/
│       └── VerifyStripeSignature.php
├── Services/
│   ├── ProvisioningService.php  # Logique de provisioning
│   └── SubscriptionService.php  # Logique upgrade/downgrade/cancel
├── Jobs/
│   ├── ProvisionServerJob.php   # Job queue pour créer serveur
│   └── SuspendServerJob.php     # Job queue pour suspendre serveur
├── Listeners/
│   └── HandleStripeEvent.php    # Dispatche vers le bon handler
└── Events/
    ├── ServerProvisioned.php
    └── ServerSuspended.php
```

#### BridgeServiceProvider

- Vérifie `config('bridge.enabled')` dans `register()`. Si `false`, ne fait rien.
- Enregistre la route `POST /webhook/stripe` (exclue du CSRF middleware)
- Enregistre les listeners et jobs

#### StripeWebhookController

- Reçoit `POST /webhook/stripe`
- `VerifyStripeSignature` middleware : vérifie la signature avec `STRIPE_WEBHOOK_SECRET` via `Stripe\Webhook::constructEvent()`
- Dispatche selon `$event->type` :
  - `checkout.session.completed` → `ProvisionServerJob::dispatch($payload)`
  - `customer.subscription.updated` → `SubscriptionService->handleUpdate($payload)`
  - `customer.subscription.deleted` → `SuspendServerJob::dispatch($payload)`

#### ProvisioningService

- `provision(array $webhookData): void`
  1. Extraire `customer_email`, `customer_name`, `line_items[0].price.id` du webhook
  2. Vérifier idempotence : `Server::where('payment_intent_id', $paymentIntentId)->exists()` → si oui, return
  3. Chercher `ServerPlan::where('stripe_price_id', $priceId)->firstOrFail()`
  4. Chercher user : `User::where('email', $customerEmail)->first()`
  5. Si user n'existe pas → `PelicanApplicationService->createUser()` + `User::create()` en DB locale
  6. Si mode local (pas OAuth) → générer mot de passe temporaire, envoyer email "Votre serveur est prêt — définissez votre mot de passe" avec lien reset
  7. `PelicanApplicationService->createServer()` avec les specs du `ServerPlan`
  8. `Server::create()` en DB locale avec `payment_intent_id`, `stripe_subscription_id`, `plan_id`
  9. Dispatch event `ServerProvisioned`

#### SubscriptionService

- `handleUpdate(array $webhookData): void` — lookup serveur par `stripe_subscription_id`, si le `price_id` a changé → lookup nouveau `ServerPlan`, mettre à jour les specs sur Pelican (pas encore de méthode pour ça dans `PelicanApplicationService` — à ajouter : `updateServerBuild()`)
- `handleCancellation(array $webhookData): void` — lookup serveur par `stripe_subscription_id`, `PelicanApplicationService->suspendServer()`, update statut en DB. **Ne suspend QUE les serveurs avec abo** (`stripe_subscription_id IS NOT NULL`).

#### Jobs

- `ProvisionServerJob` : retry 3, backoff exponentiel (10s, 30s, 90s). Appelle `ProvisioningService->provision()`.
- `SuspendServerJob` : retry 3. Appelle `SubscriptionService->handleCancellation()`.

#### Installation Stripe (composer)

Ajouter `composer require stripe/stripe-php`. Le Bridge n'utilise PAS la clé API Stripe complète — uniquement la signing secret pour vérifier les webhooks.

### Priorité 4 — Auth features manquantes

#### Forgot password

- Endpoint `POST /api/auth/forgot-password` dans `AuthController` → `Password::sendResetLink()`
- Endpoint `POST /api/auth/reset-password` → `Password::reset()`
- Pages React : `ForgotPasswordPage.tsx`, `ResetPasswordPage.tsx`
- Template email Laravel standard (customisé avec le branding Peregrine)

#### Email verification

- Endpoint `GET /api/auth/verify-email/{id}/{hash}` → marque `email_verified_at`
- Le User model implémente `MustVerifyEmail`
- Email envoyé à la création du compte (local mode uniquement)

#### Change password

- Déjà prévu dans `POST /api/user/change-password` (voir P1)
- Vérifie `current_password` avant de changer
- Désactivé en mode OAuth (pas de password)

### Priorité 4 — Emails

Tous les emails utilisent les templates Blade Laravel (`resources/views/emails/`) avec le branding Peregrine (logo, couleurs, nom app via `SettingsService`). Tous en i18n EN + FR.

- **"Votre serveur est prêt"** (`ServerReadyMail`) : envoyé par le Bridge en mode local (pas OAuth). Contient : nom serveur, IP:port, lien pour définir le mot de passe, lien vers le panel.
- **"Invitation à créer un compte Shop"** (`ShopInvitationMail`) : envoyé depuis l'admin Filament. Contient : lien vers biomebounty.com/register.
- **Reset password** : template Laravel standard customisé avec le branding.

### Priorité 4 — Plugin system

#### Backend

- Endpoint `GET /api/plugins` dans un nouveau `PluginController` : scanne les plugins activés, retourne un manifest JSON pour chaque
- Structure manifest : `{ "id": "bridge", "name": "Bridge", "version": "1.0", "nav": [{"label": "Bridge", "icon": "link", "route": "/bridge"}], "permissions": ["bridge.view"], "frontend_bundle": "/plugins/bridge/bundle.js" }`
- Les plugins sont des ServiceProviders Laravel avec des conventions de nommage

#### Frontend

- `PluginLoader.tsx` : fetch `GET /api/plugins`, pour chaque plugin avec `frontend_bundle`, lazy-load le script
- `window.PanelShared` : expose `React`, `ReactDOM`, `ReactQuery`, `i18next`, `useTranslation` pour que les plugins n'aient pas à bundler ces dépendances
- Route dynamique dans React Router : chaque plugin déclare ses routes via le manifest, le `PluginLoader` les injecte

### Priorité 5 — Tests

- **Feature tests** (`tests/Feature/`) : `AuthTest`, `SetupWizardTest`, `ServerApiTest`, `FileApiTest`, `ProfileApiTest`, `BrandingApiTest`
- **Unit tests** (`tests/Unit/`) : `PelicanApplicationServiceTest`, `PelicanClientServiceTest`, `ServerServiceTest`, `SyncServiceTest`, `SettingsServiceTest`, `SetupServiceTest`
- **Bridge tests** : `StripeWebhookTest` (idempotence, user creation, provisioning), `ProvisioningServiceTest`
- Utiliser des mocks HTTP (`Http::fake()`) pour les appels Pelican et Stripe

### Priorité 5 — Infra & DX

- **README.md** : installation Docker (3 commandes), installation classique, screenshots, contribution guide, licence
- **CI/CD** : GitHub Actions workflow — `pnpm run type-check`, `php artisan test`, `pnpm run build`
- **Bannières eggs** : images par défaut dans `public/images/eggs/` (minecraft.jpg, rust.jpg, etc.) — utilisées comme fond des cartes serveur

## Ordre d'exécution (steps restants)

Chaque step est indépendant et peut être réalisé dans une session Claude Code distincte.

### Step A : Pages Filament Apparence + migration CSS restante
1. Sidebar serveur configurable (config JSON dans settings)
2. Plugin slots : zones d'injection (sidebar entries, overview widgets, detail sections, toolbar actions)
3. Migrer composants restants vers CSS variables (setup wizard)
4. Pages Filament "Apparence" : Theme (color pickers), Cards serveurs, Sidebar serveur
5. Intégration thème dans AdminPanelProvider (couleurs dynamiques)
6. Setup wizard refonte visuelle (stepper animé, glassmorphism, cohérence design)

### Step B : UI sync avancée Filament
1. Modales comparaison avec checkboxes (au lieu de l'import automatique)
2. Bouton "Inviter sur le Shop" (mode OAuth + Bridge)
3. Matching manuel email dans fiche user

### Step C : Bridge plugin
1. `composer require stripe/stripe-php`
2. Créer `BridgeServiceProvider`, `StripeWebhookController`, `VerifyStripeSignature`
3. Créer `ProvisioningService`, `SubscriptionService`
4. Créer les jobs `ProvisionServerJob`, `SuspendServerJob`
5. Exclure la route webhook du CSRF
6. Tester avec Stripe CLI (`stripe trigger checkout.session.completed`)

### Step D : Auth complète + Emails
1. Forgot password / reset password (backend + frontend)
2. Templates email (ServerReady, ShopInvitation, ResetPassword)

### Step E : Plugin system + Widgets/Sidebar configurables
1. `GET /api/plugins`, `PluginController`
2. `PluginLoader.tsx`, `window.PanelShared`
3. Config widgets serveur (settings JSON + page Filament + frontend)
4. Config sidebar serveur (settings JSON + page Filament + frontend)

### Step F : Tests + Infra
1. Feature tests + Unit tests
2. README.md
3. CI/CD GitHub Actions
4. Commit + push GitHub

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
