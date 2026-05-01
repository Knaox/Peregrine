# Peregrine

## Projet

Panel open source de gestion de serveurs de jeux via Pelican (fork de Pterodactyl).

- **Nom** : Peregrine (titre affiché configurable par l'admin dans les paramètres Filament)
- **Logo** : SVG dans `public/images/logo.svg`, modifiable par l'admin via upload
- **URL** : games.biomebounty.com
- **Shop associé** : biomebounty.com (SaaSykit, Laravel — projet SÉPARÉ, on n'y touche JAMAIS)
- **Stack** : Laravel 13 + Filament 5 (Livewire v4) + React 19 + TypeScript + Vite + Tailwind + Scramble
- **DB** : MySQL 8, DB propre à Peregrine (séparée du Shop)
- **Queue** : Laravel Queue (Redis ou database driver)
- **Licence** : Open source — panel utilisable standalone sans le Shop BiomeBounty
- **Branding** : titre, logo, favicon modifiables dans Filament ("Paramètres > Apparence"), stockés dans la table `settings` (cachés). Défaut : "Peregrine" + logo faucon orange.

## Écosystème BiomeBounty

Deux produits INDÉPENDANTS qui communiquent via webhooks et OAuth.

### Shop (biomebounty.com)

- SaaSykit (Laravel) — code NON modifiable (hors Laravel Passport installé)
- Gère : utilisateurs, produits/plans, paiements Stripe, abonnements, facturation
- Identity Provider OAuth2 (Laravel Passport) pour Peregrine
- DB séparée

### Peregrine (games.biomebounty.com) — CE PROJET

- Laravel 13 + Filament 5 + React SPA
- Gère : serveurs de jeux, console, fichiers, stats, plugins
- Communique avec Pelican via son API
- Reçoit les webhooks Stripe directement (pas via le Shop)
- DB séparée
- Titre, logo, favicon customisables par l'admin

### Communication entre les deux

- **Auth** : OAuth2 (Shop = provider) OU login local standalone OU providers sociaux (Google/Discord/LinkedIn) — tout coexiste, l'admin pilote depuis `/admin/auth-settings`
- **Paiements** : Stripe envoie les webhooks aux DEUX indépendamment
- **Aucune dépendance directe** : si le Shop tombe, Peregrine continue. Et inversement.

## Architecture

### Authentification (multi-provider configurable)

Multi-flag depuis la table `settings` — plus de `AUTH_MODE` binaire dans `.env`.

| Setting | Description |
|---|---|
| `auth_local_enabled` | Login email+password autorisé |
| `auth_local_registration_enabled` | `/register` ouvert (forcé `false` si canonical IdP activé) |
| `auth_shop_enabled` | Provider "Shop" BiomeBounty actif (canonical IdP) |
| `auth_shop_config` | JSON : client_id, client_secret (chiffré), authorize/token/user URLs, redirect_uri |
| `auth_paymenter_enabled` | Provider Paymenter actif (canonical IdP alternatif au Shop, mutuellement exclusif) |
| `auth_paymenter_config` | JSON : base_url, client_id, client_secret (chiffré), redirect_uri, register_url, logo_path |
| `auth_providers` | JSON : Google / Discord / LinkedIn (enabled + client_id + client_secret chiffré) |
| `auth_2fa_enabled` | 2FA TOTP disponible pour tous les users |
| `auth_2fa_required_admins` | Force 2FA pour admins (impacte `canAccessPanel` + middleware `two-factor`) |

**Canonical IdPs** (Shop, Paymenter) : auto-création de comptes, sync email vers Pelican, register URL exposé sur la page de login, révalidation `email_verified` à chaque connexion. **Mutuellement exclusifs** — Filament bloque le save si les deux sont activés. Shop = SaaSykit BiomeBounty (propriétaire). Paymenter = paymenter.org (open-source, Laravel Passport, base URL unique → `/oauth/authorize`, `/api/oauth/token`, `/api/me` scope `profile`). Provisioning serveurs depuis Paymenter : extension côté Paymenter (Pelican-Paymenter sur builtbybit.com) + bridge mode + webhooks. Voir `docs/authentication.md` § Paymenter.

**2FA TOTP** : réutilise les traits natifs Filament 5 (`HasAppAuthentication`, `HasAppAuthenticationRecovery`) — colonnes `users.app_authentication_secret` (encrypted) et `users.app_authentication_recovery_codes` (encrypted:array de bcrypt hashes). Challenge pending entre password OK et code 2FA : Redis, UUID, TTL 5 min. Page de setup : `/2fa/setup` standalone, auto-redirect via interceptor HTTP quand enforcement admin déclenche un 403.

**Identités OAuth** : table `oauth_identities` (user_id, provider, provider_user_id, provider_email, last_login_at). Un user peut avoir plusieurs identités liées (match par email, seulement si `email_verified` côté provider).

**Admin mode** : `Gate::before` scopé (whitelist `Server` uniquement) dans `AuthServiceProvider`. Endpoint `/api/admin/servers` + `?view=all` sur `/api/servers`. Actions admin sur serveurs d'autres users → événement `AdminActionPerformed` → listener sync → table `admin_action_logs`. Commande `server.command` tronquée à 500 chars dans le payload audit.

**Templates email** : 5 notifications auth éditables depuis `/admin/email-templates` (2FA enabled/disabled, recovery regenerated, OAuth linked/unlinked). Variables : `{name}`, `{server_name}`, `{timestamp}`, `{ip}`, `{user_agent}`, `{manage_url}`, plus `{provider}` pour les templates OAuth.

Doc interne détaillée : `docs/auth-architecture.md`.

### Pelican (deux API distinctes)

- **Application API** (`/api/application/`) : admin. Crée/supprime users, provisionne serveurs, gère nodes/eggs/nests. Auth : `PELICAN_ADMIN_API_KEY` (JAMAIS exposée au frontend). Utilisée par le Bridge + admin Filament.
- **Client API** (`/api/client/`) : utilisateur. Liste serveurs, console, fichiers, stats, power control. Auth : `PELICAN_CLIENT_API_KEY` (une par utilisateur Pelican). Proxifiée via le backend — le frontend appelle Peregrine, Peregrine appelle Pelican.
- Mots de passe Pelican SÉPARÉS du login principal. Section "Accès SFTP" dans Peregrine pour que le joueur définisse son mot de passe SFTP dédié.

**Note admin mode** : pour que les ops admin sur serveurs d'autres users fonctionnent via la Client API, `PELICAN_CLIENT_API_KEY` doit appartenir à un compte Pelican admin (sinon Wings rejette le JWT).

### Performance & Stockage

- **Tout l'affichage vient de la DB locale** : eggs, nests, nodes, serveurs, users. Zéro appel API Pelican au chargement d'une page.
- **Sync périodique** : job schedulé toutes les 5-10 min met à jour les statuts serveur. Boutons "Sync Eggs / Nodes" dans l'admin Filament pour forcer la synchro.
- **Cache Redis** : settings/branding (TTL 1h), résultats de requêtes complexes (dashboard, stats). React : TanStack Query avec `staleTime` approprié.
- **WebSocket direct vers Wings** : console, logs, stats live. Flow : Peregrine génère un JWT via `GET /api/client/servers/{id}/websocket` → frontend connexion directe à Wings. Token refresh toutes les 10 min. Jamais en DB ni en cache. Retry policy partagée (`useWsRetryState`) : max 8 tentatives, give-up sur 4xxx (Wings refusal) ou 403/404 (credentials error).

### Sync (admin Filament + CLI)

La synchronisation Pelican se fait via l'admin Filament (boutons UI) ou via des commandes artisan.

**Pages admin** : Utilisateurs + Serveurs. Chaque page a son bouton "Sync" qui appelle `PelicanApplicationService->list*()`, compare avec la DB locale, affiche nouveau/synchro/orphelin.

**Rattachement serveur ↔ abo Shop** : les serveurs importés via sync n'ont pas d'abo. Champ "Stripe Subscription ID" + dropdown "Server Plan" dans la fiche admin pour rattacher manuellement. Serveur sans abo = jamais suspendu auto.

**ServerService** : chaque serveur a un `ServerService` dédié (start/stop/restart/suspend/unsuspend/delete/stats/files). Utilise `PelicanClientService` en interne.

**Commandes artisan** :

- `php artisan sync:users` — sync interactive users Pelican
- `php artisan sync:servers` — sync serveurs
- `php artisan sync:eggs` — sync eggs & nests
- `php artisan sync:nodes` — sync nodes
- `php artisan sync:health` — health check schedulé
- `php artisan auth:backup-oauth-legacy` — snapshot SQL avant migration oauth

### Setup Wizard

- Si `PANEL_INSTALLED` n'est pas `true`, tout redirige vers `/setup`
- SPA React autonome (pas Filament, pas Livewire)
- Étapes : Langue → DB → Compte admin → Pelican → Auth → Récap → Install (la config Bridge se fait post-install via `/admin/bridge-settings`)
- Le wizard écrit le `.env`, lance les migrations, crée le compte admin, set `PANEL_INSTALLED=true`
- Après install, wizard inaccessible (middleware `EnsureInstalled`)
- Config modifiable après coup via `/admin/settings` (écrit dans `.env`) ou `/admin/auth-settings` (écrit dans la table `settings`)

### Plugins

- Architecture micro-frontend
- Chaque plugin expose un manifest JSON via `GET /api/plugins` : id, name, version, nav, permissions, URL du bundle JS
- Peregrine lazy-load les bundles React à la demande
- Dépendances partagées via `window.PanelShared` (React, TanStack Query, react-i18next)
- Chaque plugin est un mini-projet buildé séparément (Vite → IIFE bundle)
- Routes plugin dynamiques : plugin désactivé = routes absentes = absent de la doc Scramble
- Registry externe : `Knaox/peregrine-plugins` (branche `main`), `raw.githubusercontent.com/.../registry.json`
- Plugin "invitations" livré par défaut (v0.8.1) — admin-aware via `Server::scopeAccessibleBy`

#### Plugin mail / jobs — contrat queue-safe

- **Aucune classe de plugin (Mailable, Job, Event) ne doit JAMAIS être sérialisée dans la queue.** Les payloads figés dans `jobs` survivent aux changements de code et finissent en `__PHP_Incomplete_Class` sur unserialize.
- Pour dispatcher un mail depuis un plugin : `App\Jobs\SendPluginMail::dispatch($email, Plugins\Foo\Mail\BarMail::class, [...scalaires...])`. Le Mailable est reconstruit au `handle()`.
- Le Mailable d'un plugin **ne doit PAS** implémenter `ShouldQueue`. Seul `SendPluginMail` (core) l'implémente. Marquer la classe `final`.
- Après activation/désactivation d'un plugin, `PluginManager` lance automatiquement `queue:restart` et purge les jobs orphelins.
- Résidus après refactor incompatible : `php artisan plugin:purge-stale-jobs {plugin_id}` nettoie `jobs` + `failed_jobs`.

### Documentation API

- Scramble auto-génère OpenAPI 3.1.0 sur `/docs/api`
- Scanne les routes enregistrées dynamiquement (plugins actifs inclus)
- Pour que Scramble fonctionne : typer les return types, utiliser Form Requests, utiliser API Resources

### Branding

- Titre, logo (SVG/PNG uploadable vers `storage/app/public/branding/` servi via symlink `public/storage`), favicon — tout modifiable dans `/admin/settings`
- Stocké dans la table `settings`, cache Redis 1h via `SettingsService`
- Frontend fetche via `GET /api/settings/branding` au chargement
- Un hébergeur peut rebrander entièrement sans toucher au code

### Docker (mode d'installation recommandé)

- `docker compose up -d` lance PHP-FPM 8.3, Nginx, MySQL 8, Redis
- Modes :
  - **Développement** : volumes montés, hot-reload Vite, xdebug, logs visibles
  - **Production** : images optimisées, assets pré-buildés, pas de devtools
- `Dockerfile` multi-stage : build frontend (pnpm + Vite) puis PHP-FPM avec les assets
- Setup Wizard détecte Docker (`DOCKER=true`) et pré-remplit la config DB
- Installation Docker : `git clone` → `docker compose up -d` → Setup Wizard
- Installation classique : `git clone` → `composer install` → `pnpm install` → config manuelle → Setup Wizard

## Base de données

### Tables principales

- `users` : id, email, name, password (nullable si OAuth), locale (en/fr), pelican_user_id, **stripe_customer_id (UNIQUE)**, is_admin, `app_authentication_secret` (encrypted), `app_authentication_recovery_codes` (encrypted:array bcrypt), `two_factor_confirmed_at`, timestamps
- `oauth_identities` : id, user_id FK, provider (shop/google/discord/linkedin/paymenter), provider_user_id, provider_email, last_login_at, timestamps. Unique `(provider, provider_user_id)`.
- `admin_action_logs` : id, admin_id, target_user_id (nullable), target_server_id (nullable), action, payload (json), ip, user_agent, created_at
- `servers` : id, user_id FK, pelican_server_id, identifier, name, status (enum incl. `provisioning` / `provisioning_failed`), egg_id, plan_id, **stripe_subscription_id (UNIQUE nullable)**, payment_intent_id (UNIQUE nullable), `paymenter_service_id` (string nullable, indexed — Pelican `external_id` mirroring in Bridge Paymenter mode), `idempotency_key` (UNIQUE), `provisioning_error` (text), `scheduled_deletion_at` (timestamp nullable, indexed), timestamps
- `eggs`, `nests`, `nodes` : miroirs locaux de Pelican (sync via boutons admin ou CLI). `nest` n'est plus exposée par l'API Pelican (retirée) → dérivée de `egg.nest_id` localement.
- `server_plans` : id, name, stripe_price_id (nullable, UNIQUE), egg_id (nullable), nest_id (nullable), node_id (nullable), ram, cpu, disk, swap_mb, io_weight, cpu_pinning, default_node_id, allowed_node_ids (json), auto_deploy, docker_image, port_count, env_var_mapping (json), enable_oom_killer, start_on_completion, skip_install_script, dedicated_ip, feature_limits_*, checkout_custom_fields (json), shop_plan_id (UNIQUE), shop_plan_slug, shop_plan_type, description, price_cents, currency, interval, interval_count, has_trial, trial_*, last_shop_synced_at, is_active
- `settings` : id, key (unique), value (text nullable). Stocke branding, auth config, thème, email templates, **bridge config (bridge_mode enum [disabled/shop_stripe/paymenter], bridge_enabled (legacy fallback), bridge_shop_url, bridge_shop_shared_secret encrypted, bridge_stripe_webhook_secret encrypted, bridge_grace_period_days, bridge_pelican_webhook_token encrypted)**. Cachée.
- `sync_logs` : id, type, status, summary (json), started_at, completed_at
- `bridge_sync_logs` : audit des appels Bridge plan-sync depuis le Shop (id, action, shop_plan_id, server_plan_id, request_payload json, response_status, response_body, ip_address, signature_valid, attempted_at)
- `stripe_processed_events` : idempotency ledger des webhooks Stripe (event_id PK, event_type, payload_summary json, response_status, error_message, processed_at). TTL 30j via `stripe:clean-processed-events` daily.
- `pelican_processed_events` : idempotency ledger des webhooks Pelican (Bridge Paymenter mode). PK `idempotency_hash` = sha256(event|model_id|updated_at|body). TTL 2j via `pelican:clean-processed-events` daily — Pelican ne retry pas, rétention courte suffit.

### Principes

- Peregrine a sa propre DB, JAMAIS de lecture/écriture dans la DB du Shop
- Eggs/nests/nodes dupliqués en DB locale depuis Pelican (sync push). Pelican reste la source de vérité.
- Liste serveurs, détails serveur, eggs = tout DB locale. Stats live (CPU, RAM, console) = WebSocket direct vers Wings.

## Configuration (.env)

Valeurs écrites par le Setup Wizard. Édition manuelle non requise.

```env
PANEL_INSTALLED=true|false
APP_URL=https://games.biomebounty.com

# Base de données
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

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

La config auth (providers, secrets, 2FA enforcement) vit dans la table `settings`, éditable depuis `/admin/auth-settings` — pas dans `.env`. Secrets OAuth chiffrés via `Crypt::encryptString()`.

## Règles de codage

### i18n

- Langues supportées : EN (défaut) + FR. Nouvelles langues ajoutées via le même système.
- **JAMAIS de texte en dur dans l'UI** — tout passe par des clés i18n, sans exception.
- Frontend : `react-i18next` + JSON par langue dans `resources/js/i18n/` (`en.json`, `fr.json`).
- Clés organisées par namespace/section : `"servers.list.title"`, `"auth.login.button"`, etc.
- **EN et FR TOUJOURS synchronisés dans le même commit.**
- Backend : `lang/en/` et `lang/fr/` pour emails, notifications, erreurs API, validation.
- Langue détectée via header `Accept-Language`, fallback EN. User peut changer dans son profil (colonne `users.locale`).
- Setup Wizard demande la langue en première étape.
- Convention de nommage : snake_case, anglais, hiérarchique. Ex : `servers.status.active`, `bridge.sync.success`.
- Messages d'erreur API retournent des clés i18n (ex: `{"error": "servers.not_found"}`), le frontend traduit.
- Pluralisation : `{{count}}` (i18next) / `trans_choice` (Laravel).

### Générales

- TypeScript strict, JAMAIS de `any` — utiliser `unknown` + type guards si nécessaire
- Fichiers : maximum 300 lignes. Si ça dépasse, découper.
- Chaque composant React dans son propre fichier
- Chaque props type/interface dans son propre fichier (ex: `ServerCard.props.ts`)
- Chaque hook custom dans `hooks/`, chaque service dans `services/`
- Nommage : PascalCase composants/types, camelCase fonctions/variables, UPPER_SNAKE_CASE constantes
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

- Jamais de secret/clé dans le code — tout en `.env` ou en `settings` (chiffré)
- Webhook Stripe : toujours vérifier la signature
- API Pelican : clé admin jamais exposée au frontend
- CSRF activé sur toutes les routes web
- Rate limiting sur les endpoints sensibles (`login`, `2fa-challenge`, `2fa-setup`, `social-redirect`)
- Idempotence sur tous les handlers de webhook
- `Gate::before` admin : whitelist scopée uniquement (model `Server`). Ne jamais ajouter un model sensible (billing, session, token, 2FA secret) sans revue sécurité dédiée.
- OAuth auto-linking par email : exige `email_verified` côté provider (sauf Shop = trusted).

### Git

- Pas de commit automatique — ATTENDS LA VALIDATION avant de commit
- Pas de version bump sauf si demandé explicitement
- Messages de commit en anglais, conventionnels (feat:, fix:, refactor:, etc.)
- Un commit = un changement logique

## Structure du projet

```
peregrine/
├── CLAUDE.md
├── README.md
├── docs/
│   └── auth-architecture.md      # Notes internes auth (Gate::before, ajout provider OAuth, plan §S-references)
├── Dockerfile                     # Multi-stage: build frontend + PHP-FPM
├── docker-compose.yml
├── docker-compose.prod.yml
├── docker/
│   ├── nginx/default.conf
│   ├── php/php.ini
│   └── mysql/init.sql
├── app/
│   ├── Console/Commands/          # sync:*, auth:backup-oauth-legacy, plugin:purge-stale-jobs, …
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/               # API endpoints pour le React SPA
│   │   │   │   ├── Admin/         # admin cross-user (AdminServersController)
│   │   │   │   └── Auth/          # TwoFactorController, SocialAuthController
│   │   │   ├── Setup/             # Setup Wizard API
│   │   │   └── Webhook/           # Webhook handlers (Stripe — à venir)
│   │   ├── Middleware/            # EnsureInstalled, EnsureAdmin, RequireTwoFactor
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/                    # User, Server, OAuthIdentity, AdminActionLog, …
│   ├── Events/                    # AdminActionPerformed, TwoFactorEnabled, OAuthProviderLinked, …
│   ├── Listeners/                 # LogAdminAction, SendTwoFactorEnabledNotification, …
│   ├── Notifications/             # TwoFactorEnabledNotification, OAuthProviderLinkedNotification, …
│   ├── Policies/                  # ServerPolicy
│   ├── Exceptions/Auth/           # UnverifiedEmail / RegisterOnShopFirst / LastLoginMethod / ProviderDisabled
│   ├── Filament/
│   │   ├── Resources/             # UserResource, ServerResource, EggResource, NodeResource, ServerPlanResource, SyncLogResource
│   │   └── Pages/                 # Settings, AuthSettings, ThemeSettings, EmailTemplates, Plugins, About
│   ├── Services/
│   │   ├── Pelican/               # PelicanApplicationService, PelicanClientService, PelicanFileService, …
│   │   ├── Auth/                  # TwoFactorService, TwoFactorChallengeStore, AuthProviderRegistry, SocialUserMatcher, SocialAuthService, ShopSocialiteProvider
│   │   ├── Admin/                 # AdminAuditService
│   │   ├── Mail/                  # MailTemplateRegistry, MailTemplateService
│   │   ├── ServerService.php
│   │   ├── SyncService.php
│   │   ├── SettingsService.php
│   │   └── ThemeService.php
│   ├── Jobs/                      # SendPluginMail, SyncServerStatusJob, …
│   ├── Providers/                 # AppServiceProvider, AuthServiceProvider, SocialAuthServiceProvider, Filament\AdminPanelProvider
│   └── Plugins/                   # Plugin core (Bridge à venir)
├── config/
│   ├── panel.php
│   └── bridge.php                 # À venir (P3)
├── lang/{en,fr}/                  # auth.php, servers.php, bridge.php, validation.php
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── plugins/
│   └── invitations/               # Plugin invitations (v0.8.1)
├── marketplace/
│   └── registry.json              # Registry externe (copié vers Knaox/peregrine-plugins)
├── resources/
│   ├── js/                        # React SPA
│   │   ├── app.tsx
│   │   ├── components/            # auth/, admin/, server/, profile/, …
│   │   ├── hooks/                 # useAuthProviders, useTwoFactor, useAdminServers, useWsRetryState, …
│   │   ├── pages/                 # LoginPage, DashboardPage, TwoFactorSetupPage, AdminServersPage, settings/SecurityPage, …
│   │   ├── services/              # api.ts, authApi.ts, adminApi.ts, http.ts
│   │   ├── stores/                # authStore (Zustand)
│   │   ├── types/
│   │   ├── plugins/
│   │   ├── setup/                 # Setup Wizard SPA
│   │   └── i18n/                  # en.json / fr.json (synchro)
│   └── views/
│       ├── app.blade.php
│       ├── emails/templated.blade.php
│       ├── mail/layouts/
│       └── filament/pages/
├── public/
│   └── images/
│       ├── logo.svg
│       └── favicon.svg
├── routes/
│   ├── api.php
│   └── web.php
├── tests/
│   ├── Feature/                   # TwoFactorTest, AdminServerManagementTest, SocialAuthTest, …
│   └── Unit/
└── .env.example
```

## Filament v5 Reference

Filament v5 a des changements de namespace significatifs vs v3/v4. Namespaces corrects vérifiés contre le vendor.

### Namespace Changes (v3/v4 → v5)

**Actions (MAJOR CHANGE)** — table actions moved from `Filament\Tables\Actions\*` to `Filament\Actions\*`:

- `Filament\Actions\EditAction`
- `Filament\Actions\DeleteAction`
- `Filament\Actions\ViewAction`
- `Filament\Actions\CreateAction`
- `Filament\Actions\BulkActionGroup`
- `Filament\Actions\DeleteBulkAction`
- `Filament\Actions\Action` (générique — boutons submit de form aussi)

**Form Components (mostly unchanged)** — restent dans `Filament\Forms\Components\*`:

- `TextInput`, `Select`, `Toggle`, `Radio`, `Textarea`, `Checkbox`, `DatePicker`, `FileUpload`, `Repeater`

**Layout Components (MOVED to Schemas)** — déplacés vers `Filament\Schemas\Components\*`:

- `Section` (was `Forms\Components\Section`)
- `Grid`, `Tabs`, `Fieldset`, `Wizard`

**Schema (replaces Form)** : `Filament\Schemas\Schema` remplace `Filament\Forms\Form` dans `Resource::form()`.

**Utility Classes (MOVED)** : `Filament\Schemas\Components\Utilities\Get` (was `Filament\Forms\Get`).

**Table Columns (unchanged)** : `TextColumn`, `IconColumn`, `BadgeColumn`, `BooleanColumn`, `ToggleColumn`, `ImageColumn`.

**Table Filters (unchanged)** : `TernaryFilter`, `SelectFilter`.

**Widgets (unchanged)** : `StatsOverviewWidget`, `StatsOverviewWidget\Stat`, `TableWidget`, `ChartWidget`.

**Pages (unchanged)** : `Resources\Pages\ListRecords`, `CreateRecord`, `EditRecord`, `Pages\Page`, `Pages\Dashboard`.

**Autres (unchanged)** : `Resources\Resource`, `PanelProvider`, `Panel`, `Tables\Table`, `Forms\Concerns\InteractsWithForms`, `Forms\Contracts\HasForms`.

### Resource Method Signatures (v5)

```php
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MyResource extends Resource
{
    // form() prend Schema, plus Form
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([...]);
    }

    // table() inchangé
    public static function table(Table $table): Table
    {
        return $table->columns([...])->filters([...])->actions([...])->bulkActions([...]);
    }
}
```

### Deprecated Methods in v5

- `Table::bulkActions()` deprecated → use `Table::toolbarActions()`
- `Table::actions()` = alias de `Table::recordActions()` (les deux fonctionnent)

### Common Gotchas vs v3/v4

1. **Section moved** : `Forms\Components\Section` n'existe pas. Use `Schemas\Components\Section`.
2. **Actions moved** : `Tables\Actions\EditAction` etc. n'existent pas. Use `Filament\Actions\EditAction`.
3. **Form → Schema** : signature de `form()` est `Schema $schema`, plus `Form $form`.
4. **Get utility moved** : `Forms\Get` n'existe pas. Use `Schemas\Components\Utilities\Get`.
5. **Form inputs stayed** : `TextInput`, `Select`, `Toggle`, `Radio` etc. restent dans `Forms\Components\*`. Seuls les layout components (Section, Grid, Tabs, Fieldset) sont dans `Schemas`.
6. **Form submit actions** : use `Filament\Actions\Action` pour les boutons de form.

### Filament 5 MFA natif

Contracts : `Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication` + `HasAppAuthenticationRecovery`. Traits : `InteractsWithAppAuthentication` + `InteractsWithAppAuthenticationRecovery`. Auto-gèrent casts (`encrypted`, `encrypted:array`) + hidden attrs. User model de Peregrine les utilise pour partager les colonnes entre panel Filament et SPA React.

## Reste à faire

Aucune dette critique.

- **Theme Studio v1 + Vague 3 complète + Refinements** **livré 2026-04-29** (Vagues 1, 3 démarrage, 3 complète, parité Filament, plus de perso, bug fixes — voir `## Theme Studio` ci-dessous).

Backlog optionnel (à faire seulement si besoin produit explicite) :
- OneTimeProduct sync depuis le Shop (endpoints dédiés `/api/bridge/one-time-products/upsert`) — quand le Shop commencera à pousser des produits one-shot
- Endpoint `/api/bridge/stripe-price-rotated` pour notifier Peregrine quand le Shop crée un nouveau Price (rotation grandfathering) — actuellement l'admin doit re-mapper manuellement le `stripe_price_id` côté plan
- Migration vers Horizon quand le volume Stripe le justifie (cf. `docs/operations/queue-worker.md` § Future)
- Cleanup migration pour drop la setting legacy `bridge_enabled` (gardée 1 release pour fallback de `BridgeModeService::current()`)
- Surface `paymenter_service_id` dans l'admin servers (column + filter) — utile au support pour relier Server local ↔ service Paymenter

### Theme Studio — prochaines vagues (planifiées)

**Vague 4 — Marketplace de thèmes (~3-4j)** — recommandé en premier
- Sauvegarde / import / export d'un thème complet en JSON (tout `theme_*` + card_config + sidebar_config dans un blob)
- Fork d'un preset existant (duplique → renomme → édite)
- Marketplace communautaire (même pattern que `Knaox/peregrine-plugins` — registry distant `Knaox/peregrine-themes`, branche `main`, `raw.githubusercontent.com/.../registry.json`)
- "Theme from logo" — extrait les couleurs dominantes du `branding.app_logo_path` et propose une palette
- AI assist (optionnel) — "génère-moi un thème fintech sombre" → LLM via Claude API

**Vague 2 — Token system v2 (~5j)**
- Échelles 50→950 par couleur (auto-générées en HSL à partir d'une teinte)
- Typo fine : weights / line-heights / letter-spacing par rôle (display, h1-h6, body, caption, label) — Material 5 type roles
- 5 niveaux d'ombres paramétrables (offset / blur / opacity)
- Gradients réutilisables (l'admin définit 3-5 gradients nommés, les composants les consomment)
- Background patterns app : déjà fait, mais ajouter des background patterns par-section (header / sidebar / cards) au-delà du global

**Vague 5 — Polish & accessibilité (~3-4j)**
- Contrast checker live : badge WCAG AA/AAA sur chaque couple texte/fond + auto-fix suggéré
- Color-blind simulator (deutéranopie / protanopie / tritanopie) en preview
- Monaco editor pour `theme_custom_css` (au lieu du textarea actuel) — autocomplete sur les `--color-*` etc.
- Preview email — voir le rendu des templates d'emails avec le branding appliqué
- Per-user overrides : laisser l'utilisateur final ajuster sa propre taille de police, mode dark/light, density (dans `/settings/appearance`)

**Phase 1D-E — Polish studio (~1.5j)**
- Workflow draft / publish avec brouillon persisté en DB (au lieu de saving direct)
- Undo/redo avec historique des 20 derniers changements (Cmd+Z / Cmd+Shift+Z)

**Filament parity reverse (~2j)**
- Ajouter sidebar avancée + login templates + footer + refinements + page overrides à `/admin/theme-settings` Filament pour parité avec studio (pas urgent — le studio est l'entry-point principal).

### Sources de bugs connus (à surveiller)

- **Tailwind v4** : `flex-shrink-0` deprecated, certains selectors CSS `aside.relative.flex-shrink-0` ne matchent plus → toujours utiliser une classe explicite (`server-sidebar`, `dashboard-cards-grid`, etc.) pour les overrides CSS.
- **Laravel 11+** : disk `local` root est `storage/app/private` (pas `storage/app`). Pour exposer via `/storage`, **toujours utiliser `Storage::disk('public')`** (root = `storage/app/public`).
- **CSRF post-rotation** : `getCsrfHeaders()` dans `services/http.ts` envoie soit `X-XSRF-TOKEN` (cookie, frais) soit `X-CSRF-TOKEN` (meta, peut être stale). **Jamais les deux** — Laravel rejette en 419 si X-CSRF-TOKEN est rempli avec une valeur cookie encryptée.
- **Theme propagation en preview** : tous les consommateurs doivent passer par `useResolvedTheme()` (lit `ThemeContext` exposé par `ThemeProvider`), pas par leur propre `useQuery(['theme'])`. Sinon en mode preview iframe, ils lisent l'API en cache au lieu du postMessage du studio.

## Theme Studio — architecture (livré 2026-04-29)

Page admin React full-screen à `/theme-studio` (entrée via Filament `/admin/theme-settings` → bouton "Open Theme Studio"). Live preview en split-screen : panneau d'édition à gauche, iframe preview à droite synchronisée par `postMessage`.

### Vue d'ensemble

**Entry point** : `app/Filament/Pages/ThemeSettings.php` (header action `open_studio` qui ouvre `/theme-studio`).

**Page React** : `resources/js/pages/admin/ThemeStudioPage.tsx` (admin-only, hors `AppLayout`, route enregistrée dans `app.tsx` à l'intérieur de `<ProtectedRoute>`).

**Hook central** : `resources/js/hooks/useThemeStudio.ts` (244 lignes) — gère 3 drafts en mémoire (`ThemeDraft` flat + `CardConfig` + `SidebarConfig`), envoie le payload combiné en `postMessage` à l'iframe à chaque édition (debounced via React state batching), POST `/api/admin/theme/save` au clic "Publier".

### Backend

| Fichier | Rôle |
|---|---|
| `app/Filament/Pages/Theme/ThemeDefaults.php` | Defaults source-of-truth pour TOUS les `theme_*` settings (~60 clés au total). Ajouter une clé ici la rend automatiquement éditable + persistable + reset-able. |
| `app/Services/ThemeService.php` (196 lignes) | `getTheme()` agrège les colonnes en sous-clés (`colors`, `layout`, `sidebar_advanced`, `login`, `page_overrides`, `footer`, `refinements`, `app`). Cache Redis 1 h. |
| `app/Services/Theme/ThemeAdvancedSettings.php` (extracted helper, 117 lignes) | Build les sous-sections nouvelles de Vague 3+ (layout / sidebar / login / page_overrides / footer / refinements / app). Garde ThemeService sous 200 lignes. |
| `app/Services/Theme/CssVariableBuilder.php` (232 lignes) | Convertit le tableau de thème en map `--key: value` consommé par le `<style>:root>` et l'iframe via `setProperty`. Émet ~50 vars (`--color-*`, `--radius-*`, `--font-sans`, `--shadow-intensity`, `--density-scale`, `--layout-*`, `--sidebar-*`, `--transition-base`, `--hover-scale`, `--border-width`, `--glass-blur`, `--font-size-base`). |
| `app/Http/Controllers/Api/Admin/AdminThemeController.php` (203 lignes) | Endpoints `/api/admin/theme/*` : `state`, `presets`, `save`, `reset`, `upload-asset`. Tous `admin` middleware. **Important** : `uploadAsset` utilise `Storage::disk('public')` (PAS le défaut `local` qui pointe vers `storage/app/private` depuis Laravel 11). |
| `app/Http/Requests/Admin/SaveThemeRequest.php` (107 lignes) | Validation de tous les `theme_*` + `card_config` + `sidebar_config` + `theme_footer_links` (array of {label, url}). |
| `app/Http/Requests/Admin/UploadThemeAssetRequest.php` | Multipart upload validation (image, max 5 MB, mimes JPG/PNG/WEBP). Slot enum (actuellement `login_background`, extensible). |

### Frontend — bridge & state management

| Fichier | Rôle |
|---|---|
| `resources/js/components/ThemeProvider.tsx` (179 lignes) | **SOURCE DE VÉRITÉ unique** du thème résolu. Crée UN seul `useThemePreviewBridge` (postMessage listener), expose le résultat via `ThemeContext`. Applique CSS vars + data-attrs (`data-theme`, `data-header-sticky/align`, `data-sidebar-floating`, `data-page-*`) sur `<html>`. |
| `resources/js/hooks/useResolvedTheme.ts` (24 lignes) | `useContext(ThemeContext)`. **TOUS les consommateurs (useCardConfig, useSidebarConfig, AppLayout, LoginPage, etc.) DOIVENT passer par lui** — pas de useQuery `['theme']` indépendant, sinon en preview mode ils lisent l'API en cache et ratent les postMessage updates. |
| `resources/js/hooks/useThemePreviewBridge.ts` | Détecte `?preview=1`, écoute `peregrine:theme:update` / `peregrine:theme:setMode`, envoie `peregrine:theme:ready` au parent au mount. Origin-checked. |
| `resources/js/lib/themeStudio/buildPreviewVariables.ts` (188 lignes) | TS mirror de `CssVariableBuilder.php` — calcul instantané des CSS vars côté studio sans round-trip API. **À garder en sync avec le PHP** sinon le live preview diverge du rendu réel après save. |
| `resources/js/lib/themeStudio/buildModeVariants.ts` | Compose `mode_variants: {dark, light}` pour que le toggle dark/light du toolbar studio fonctionne (active mode = couleurs draft, inverse mode = couleurs preset). |

### Frontend — UI du studio

Toutes dans `resources/js/components/admin/theme-studio/` :

- `ThemeEditorPanel.tsx` (252 lignes — proche de la limite, splitter si on rajoute) — orchestrateur des sections
- `ThemePresetSelector.tsx` — picker visuel des 7 presets (Orange/Amber/Crimson/Emerald/Indigo/Violet/Slate)
- `ThemeLayoutSection.tsx` — header height/sticky/align + container max + page padding
- `ThemeSidebarSection.tsx` — sidebar widths (classic/rail/mobile) + blur + floating toggle
- `ThemeSidebarNavSection.tsx` — sidebar legacy (position/style/show toggles + repeater entrées avec ▲▼)
- `ThemeCardsSection.tsx` — 14 fields server cards (8 visibility toggles + style + sort + group + 3 sliders colonnes)
- `ThemeLoginSection.tsx` — template + image upload + blur + pattern
- `ThemePagesSection.tsx` — 3 toggles per-page overrides (console/files fullwidth + dashboard 4 cols)
- `ThemeFooterSection.tsx` — toggle + textarea + repeater de liens
- `ThemeRefinementsSection.tsx` — animation speed + hover scale + border width + glass blur global + font size scale + app background pattern
- `ThemePreviewToolbar.tsx` — switcher 8 scènes (4 user + 4 server) + mode dark/light + breakpoint mobile/tablet/desktop
- `ThemePreviewFrame.tsx` — iframe wrapper avec `?preview=1` + `key` qui force remount au switch
- `fields/ColorField.tsx`, `SelectField.tsx`, `SliderField.tsx`, `ToggleField.tsx`, `TextareaField.tsx`, `ImageUploadField.tsx` — composants atomiques

### Frontend — login templates

`resources/js/pages/LoginPage.tsx` est devenu un dispatcher pur (53 lignes) qui rend l'un des 4 templates :

- `auth/templates/LoginCenteredTemplate.tsx` — défaut (animated gradient + particles + glow + glass card)
- `auth/templates/LoginSplitTemplate.tsx` — form gauche / image droite
- `auth/templates/LoginOverlayTemplate.tsx` — image plein écran + form en glass card flottante
- `auth/templates/LoginMinimalTemplate.tsx` — solid bg + form, sans décoration

`auth/LoginFormCard.tsx` (290 lignes — proche limite) — partagé par les 4 templates, gère social buttons + form local + register link. 3 variants visuels (`glass` / `solid` / `flush`).

`auth/LoginBackgroundLayer.tsx` — applique `.bg-pattern-{none/gradient/mesh/dots/grid/aurora/orbs/noise}` en couche absolute. Utilisé aussi par AppLayout pour le pattern global de l'app.

### CSS

4 fichiers dans `resources/css/` (chacun <300 lignes) :

- `app.css` (52 lignes) — imports, body, scrollbar, classe utilitaire `.scale-on-hover` (consomme `--hover-scale`)
- `theme.css` (352 lignes — pré-existant, pas touché) — defaults des CSS vars `:root`
- `theme-layout.css` (135 lignes) — règles layout shell (`.app-container`, `.app-header`, `.app-page`, sticky/align via data-attrs, sidebar floating, per-page overrides, dashboard 4 cols)
- `bg-patterns.css` (113 lignes) — 8 patterns réutilisables (`.bg-pattern-X`)
- `theme-studio.css` (60 lignes) — slider track/thumb + iframe entrance fade-in

### Endpoints API

Tous sous `/api/admin/theme` (middleware `admin`) :

| Méthode | Path | Rôle |
|---|---|---|
| GET | `/state` | Charge le draft initial (lecture brute des `theme_*` settings) + card_config + sidebar_config |
| GET | `/presets` | Liste les 7 brand presets avec leurs valeurs dark + light |
| POST | `/save` | Persiste tout + clear cache + invalide query `['theme']` |
| POST | `/reset` | Reset toutes les `theme_*` aux defaults de `ThemeDefaults::COLORS` |
| POST | `/upload-asset` | Multipart, slot enum, retourne `/storage/branding/{slot}/{hash}.{ext}` |

### Pièges à connaître

1. **Tailwind v4** : `hover:scale-X` est hardcodé dans certains composants, ne consomme PAS `--hover-scale`. Solution : utiliser la classe utilitaire `.scale-on-hover` qui pile lit la var.
2. **Storage Laravel 11** : disk `local` root = `storage/app/private`. Pour les uploads exposés via `/storage`, utiliser `Storage::disk('public')`.
3. **CSS var blur** : `backdrop-filter: blur(var(--xxx))` doit avoir un fallback `var(--xxx, 12px)` sinon Safari peut casser le rendu si la var n'est pas encore set.
4. **Mode preview** : NE JAMAIS faire un `useQuery(['theme'])` indépendant dans un consommateur. Toujours `useResolvedTheme()`.
5. **Filament parity** : la page Filament `ThemeFormSchema` n'a PAS les nouveaux contrôles (sidebar avancée, login templates, footer, refinements, page overrides). Reste fonctionnelle (defaults safe), mais le studio est l'entry-point principal. Sync à faire en Vague 5 polish.

## Bridge — implémentation actuelle (P3 + Paymenter)

Module qui automatise le provisioning de serveurs en se synchronisant avec un shop externe. **Deux backends mutuellement exclusifs** sélectionnés via le radio `bridge_mode` dans `/admin/bridge-settings` :

- `disabled` — aucun bridge actif
- `shop_stripe` — Shop SaaSykit (ou custom) pousse les plans + Stripe envoie les webhooks (cas du Shop BiomeBounty)
- `paymenter` — Paymenter orchestre tout, Pelican forward ses events natifs vers Peregrine en mirror only (no plans page, no emails Peregrine)

L'enum `App\Enums\BridgeMode` + le service `App\Services\Bridge\BridgeModeService::current()` sont la source de vérité (avec fallback legacy sur `bridge_enabled` boolean pour les installs pre-migration).

### Mode 1 : Shop+Stripe — Trois canaux indépendants

```
SHOP                            PEREGRINE                      STRIPE
  │                                 │                            │
  │  POST /api/bridge/plans/upsert  │                            │
  ├────────────────────────────────▶│  ← stocke plan mirror      │
  │  HMAC-signé, idempotent         │                            │
  │                                 │                            │
  │  DELETE /api/bridge/plans/{id}  │                            │
  ├────────────────────────────────▶│  ← désactive (soft)        │
  │                                 │                            │
  │                                 │  POST /api/stripe/webhook  │
  │                                 │◀───────────────────────────┤
  │                                 │  4 events lifecycle        │
```

### Côté plan-sync (Shop → Peregrine)

- `POST /api/bridge/ping` — health check
- `POST /api/bridge/plans/upsert` — Shop pousse un plan complet (business + Pelican specs)
- `DELETE /api/bridge/plans/{shop_plan_id}` — soft-désactive
- HMAC-SHA256 + replay protection 5min via `VerifyBridgeSignature`
- Settings `bridge_enabled` + `bridge_shop_url` + `bridge_shop_shared_secret` (chiffré) dans `/admin/bridge-settings`
- Audit complet dans `bridge_sync_logs` (visible dans `/admin/bridge-sync-logs`)
- Doc publique `/docs/bridge-api`

### Côté Stripe webhook (Stripe → Peregrine)

- `POST /api/stripe/webhook` — 4 events traités : `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.payment_failed`
- Signature via SDK officiel `Stripe\Webhook::constructEvent()` — middleware `VerifyStripeSignature`
- Idempotence par `event.id` dans `stripe_processed_events` (TTL 30j via cron daily `stripe:clean-processed-events`)
- Settings `bridge_stripe_webhook_secret` (chiffré) + `bridge_grace_period_days` (default 14, range 0-90) dans `/admin/bridge-settings`

### Jobs queued (database driver)

- `ProvisionServerJob` (`tries=3`, backoff `[60s, 300s, 900s]`) : 2-phase commit local + Pelican, idempotence par `idempotency_key` unique sur `servers`. Dispatche `ServerProvisioned` event après succès.
- `SubscriptionUpdateJob` : upgrade/downgrade via `PelicanApplicationService::updateServerBuild()`. Soft suspend sur `past_due`, recovery sur retour à `active`.
- `SuspendServerJob` : suspend Pelican + status local `suspended`. Si `scheduleDeletion=true` → set `Server::scheduled_deletion_at = now() + grace_period`. Dispatche `ServerSuspended` event.
- `PurgeScheduledServerDeletionsJob` : cron daily à 03:00, hard-delete des servers passé leur grace period.

### Emails

3 templates dans `MailTemplateRegistry` (group "Bridge", visible dans `/admin/email-templates` uniquement quand Bridge actif) :
- `bridge_server_ready_local` — server prêt + lien reset password (user local, sans OAuth)
- `bridge_server_ready_oauth` — server prêt sans password (user OAuth)
- `bridge_server_suspended` — suspension + scheduled deletion at X

Détection mode local vs OAuth via `$user->oauthIdentities()->exists()`. Reset URL signé via `Password::broker()->createToken()`.

### Pelican client étendu

`PelicanInfrastructureClient` ajoute :
- `createServerAdvanced(CreateServerRequest)` — payload complet (limits, feature_limits, environment, allocations, oom, start_on_completion, skip_scripts)
- `updateServerBuild(int, array)` — pour upgrades de plan
- `listNodeAllocations(int)` — pour `PortAllocator`
- `getEggVariableDefaults(int)` — fetch les env vars de l'egg pour le provisioning

Services Bridge :
- `PortAllocator::findConsecutiveFreePorts(nodeId, count, ?preferredRange)`
- `EnvironmentResolver::resolve(plan, allocatedPorts, eggDefaults)` — gère `env_var_mapping` (offset/random/static)

### Worker queue obligatoire

Sans worker actif (`php artisan queue:work`), les jobs s'accumulent dans la table `jobs` et ne se traitent jamais. Setup supervisor / systemd dans `docs/operations/queue-worker.md`.

### Action admin "Cancel scheduled deletion"

Dans `/admin/servers`, action per-row visible si `scheduled_deletion_at !== null`. Permet de récupérer un serveur pendant la grace period (client qui se ravise).

### Tests

Bridge Shop+Stripe :
- `tests/Feature/BridgePlanSyncTest.php` (11 tests)
- `tests/Feature/StripeWebhookTest.php` (8 tests)
- `tests/Feature/SubscriptionLifecycleTest.php` (7 tests)
- `tests/Feature/Bridge/ServerNotificationTest.php` (5 tests)
- `tests/Unit/Bridge/PortAllocatorTest.php` (6 tests)
- `tests/Unit/Bridge/EnvironmentResolverTest.php` (7 tests)
- `tests/Feature/PelicanEmailSyncTest.php` (1 test)

Bridge Paymenter + mode commun :
- `tests/Feature/BridgeSettingsTest.php` (6 tests — radio mode, legacy fallback, token encryption)
- `tests/Feature/PelicanWebhookTest.php` (8 tests — token, mode gate, idempotence, dispatch)
- `tests/Feature/Bridge/PelicanMirrorSyncTest.php` (8 tests — mirror sync, reconciliation)
- `tests/Feature/Bridge/PelicanWebhookLogVisibilityTest.php` (3 tests — admin nav visibility)

Tous mockent Pelican via `Http::fake()` et la queue via `Bus::fake()`.

### Mode 2 : Paymenter — Pelican webhook driven (mirror only)

Pour les setups où **Paymenter** est le front-shop. L'extension Pelican-Paymenter crée/suspend/supprime les serveurs Pelican, et Pelican forward ses events natifs vers Peregrine via `/admin/webhooks` (Pelican ≥ 0.46).

- `POST /api/pelican/webhook` — receiver, middleware `VerifyPelicanWebhookToken` (Bearer token, pas de HMAC car Pelican ne signe pas)
- 5 events à activer côté Pelican (UI labels) : `created: Server`, `updated: Server`, `deleted: Server`, `created: User`, `event: Server\Installed`. ⚠️ Bug connu sur certaines versions Pelican : `event: Server\Installed` peut faire crasher la queue Pelican avec `Cannot use object as array` (`ProcessWebhook.php:40`). Si l'admin voit ces failed jobs côté Pelican, décocher uniquement cet event — `updated: Server` couvre déjà le cas install-finished (status `installing` → `null`).
- Idempotence par `sha256(event|model_id|updated_at|body)` dans `pelican_processed_events` (TTL 2j via `pelican:clean-processed-events` daily à 03:45)
- Settings `bridge_pelican_webhook_token` (chiffré) dans `/admin/bridge-settings` (section Paymenter, collapsée par défaut)
- Audit visible dans `/admin/pelican-webhook-logs` (uniquement en mode paymenter)
- Doc opérateur publique `/docs/bridge-paymenter`

Jobs queued :
- `App\Jobs\Bridge\SyncServerFromPelicanWebhookJob` : `updateOrCreate` Server local + sync pivot `server_user`. **Aucun event Peregrine fired** (pas de `ServerProvisioned`/`ServerSuspended`) — Paymenter envoie ses propres emails, on ne dédoublonne PAS.
- `App\Jobs\Bridge\SyncUserFromPelicanWebhookJob` : `updateOrCreate` User local sans password (le user se connecte via OAuth Paymenter ou reset password local).

Filet de sécurité : `SyncServerStatusJob` (cron every 5 min) appelle `reconcilePaymenterMirror()` quand `BridgeMode::isPaymenter()` — diff `PelicanApplicationService::listServers()` avec la DB locale → dispatch jobs pour les manquants, soft-delete des orphelins. Couvre les events que Pelican aurait raté (Pelican ne retry pas).

Visibility en mode Paymenter :
- `ServerPlanResource` (Plans admin) — **caché**, Paymenter gère le catalogue
- `BridgeSyncLogResource` (Bridge sync logs) — **caché**, n'a aucun sens
- `EmailTemplates` — Bridge templates (`bridge_server_ready_*`, `bridge_server_suspended`) **filtrés out**
- `PelicanWebhookLogResource` — **visible** (audit des events reçus)
- `BridgeSettings` — visible dans les 2 modes

### Étapes optionnelles d'installation

Selon le mode choisi, certaines étapes ne sont pas nécessaires :

| Étape | Shop+Stripe | Paymenter | Commentaire |
|---|---|---|---|
| Installer `stripe/stripe-php` | ✅ requis | ⏸ optionnel | Pas utilisé en mode Paymenter |
| Configurer Stripe webhook secret | ✅ requis | ⏸ ignoré | Le champ reste visible mais non utilisé |
| Configurer Shop HMAC secret | ✅ requis | ⏸ ignoré | Idem |
| Configurer Pelican `/admin/webhooks` | ⏸ ignoré | ✅ requis | Voir `/docs/bridge-paymenter` |
| Configurer email templates Bridge | ✅ requis | ❌ caché | Paymenter envoie tous les emails |
| Setup queue worker | ✅ requis | ✅ requis | Les 2 modes dispatch des jobs |
| Setup grace period | ✅ utile | ⏸ ignoré | Paymenter gère ses cycles de service |

## Commandes utiles

```bash
# Docker
docker compose up -d              # Lancer l'environnement complet
docker compose down               # Arrêter l'environnement
docker compose logs -f app        # Voir les logs PHP
docker compose exec app bash      # Shell dans le container PHP

# Setup & Installation (sans Docker)
composer install                  # Installer les dépendances PHP
pnpm install                      # Installer les dépendances JS
pnpm run dev                      # Lancer Vite en mode dev
pnpm run build                    # Build production
pnpm run type-check               # TypeScript check

# Base de données
php artisan migrate               # Lancer les migrations
php artisan migrate:fresh --seed  # Reset DB + seed

# Sync Pelican
php artisan sync:users            # Sync interactive users
php artisan sync:servers          # Sync serveurs
php artisan sync:eggs             # Sync eggs & nests
php artisan sync:nodes            # Sync nodes
php artisan sync:health           # Health check

# Auth ops
php artisan auth:backup-oauth-legacy  # Snapshot SQL pré-migration OAuth (forensic)

# Queue
php artisan queue:work            # Worker pour les jobs async

# Tests
php artisan test                  # Full suite
php artisan test --filter=XxxTest # Ciblé

# Documentation API
# Accessible sur /docs/api (auto-généré par Scramble)
```
