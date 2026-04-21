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
| `auth_local_registration_enabled` | `/register` ouvert (forcé `false` si Shop activé) |
| `auth_shop_enabled` | Provider "Shop" BiomeBounty actif (sync Pelican email) |
| `auth_shop_config` | JSON : client_id, client_secret (chiffré), authorize/token/user URLs, redirect_uri |
| `auth_providers` | JSON : Google / Discord / LinkedIn (enabled + client_id + client_secret chiffré) |
| `auth_2fa_enabled` | 2FA TOTP disponible pour tous les users |
| `auth_2fa_required_admins` | Force 2FA pour admins (impacte `canAccessPanel` + middleware `two-factor`) |

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
- Étapes : Langue → DB → Compte admin → Pelican → Auth → Bridge (optionnel) → Récap → Install
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

- `users` : id, email, name, password (nullable si OAuth), locale (en/fr), pelican_user_id, stripe_customer_id, is_admin, `app_authentication_secret` (encrypted), `app_authentication_recovery_codes` (encrypted:array bcrypt), `two_factor_confirmed_at`, timestamps
- `oauth_identities` : id, user_id FK, provider (shop/google/discord/linkedin), provider_user_id, provider_email, last_login_at, timestamps. Unique `(provider, provider_user_id)`.
- `admin_action_logs` : id, admin_id, target_user_id (nullable), target_server_id (nullable), action, payload (json), ip, user_agent, created_at
- `servers` : id, user_id FK, pelican_server_id, identifier, name, status, egg_id, plan_id, stripe_subscription_id, payment_intent_id, timestamps
- `eggs`, `nests`, `nodes` : miroirs locaux de Pelican (sync via boutons admin ou CLI)
- `server_plans` : id, name, stripe_price_id, egg_id, nest_id, ram, cpu, disk, node_id, is_active
- `settings` : id, key (unique), value (text nullable). Stocke branding, auth config, thème, email templates. Cachée.
- `sync_logs` : id, type, status, summary (json), started_at, completed_at

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

- **P3** — Bridge plugin (détail ci-dessous)

## Priorité 3 — Bridge plugin

Module optionnel qui automatise le provisioning de serveurs quand un client achète via Stripe.

### Structure

```
app/Plugins/Bridge/
├── BridgeServiceProvider.php       # Enregistrement conditionnel
├── Config/bridge.php
├── Http/
│   ├── Controllers/
│   │   └── StripeWebhookController.php
│   └── Middleware/
│       └── VerifyStripeSignature.php
├── Services/
│   ├── ProvisioningService.php     # Logique de provisioning
│   └── SubscriptionService.php     # Upgrade/downgrade/cancel
├── Jobs/
│   ├── ProvisionServerJob.php
│   └── SuspendServerJob.php
├── Listeners/
│   └── HandleStripeEvent.php
└── Events/
    ├── ServerProvisioned.php
    └── ServerSuspended.php
```

### BridgeServiceProvider

- Vérifie `config('bridge.enabled')` dans `register()`. Si `false`, ne fait rien.
- Enregistre la route `POST /webhook/stripe` (exclue du CSRF middleware)
- Enregistre listeners et jobs

### StripeWebhookController

- Reçoit `POST /webhook/stripe`
- `VerifyStripeSignature` middleware : vérifie la signature avec `STRIPE_WEBHOOK_SECRET` via `Stripe\Webhook::constructEvent()`
- Dispatche selon `$event->type` :
  - `checkout.session.completed` → `ProvisionServerJob::dispatch($payload)`
  - `customer.subscription.updated` → `SubscriptionService->handleUpdate($payload)`
  - `customer.subscription.deleted` → `SuspendServerJob::dispatch($payload)`

### ProvisioningService

- `provision(array $webhookData): void`
  1. Extraire `customer_email`, `customer_name`, `line_items[0].price.id` du webhook
  2. Vérifier idempotence : `Server::where('payment_intent_id', $paymentIntentId)->exists()` → si oui, return
  3. Chercher `ServerPlan::where('stripe_price_id', $priceId)->firstOrFail()`
  4. Chercher user : `User::where('email', $customerEmail)->first()`
  5. Si user n'existe pas → `PelicanApplicationService->createUser()` + `User::create()` en DB locale
  6. Si mode local (pas OAuth) → générer mot de passe temporaire + envoyer email "Serveur prêt — définissez votre mot de passe" avec lien reset
  7. `PelicanApplicationService->createServer()` avec les specs du `ServerPlan`
  8. `Server::create()` en DB locale avec `payment_intent_id`, `stripe_subscription_id`, `plan_id`
  9. Dispatch event `ServerProvisioned`

### SubscriptionService

- `handleUpdate(array $webhookData): void` — lookup serveur par `stripe_subscription_id`, si `price_id` a changé → nouveau `ServerPlan`, update specs Pelican (méthode `updateServerBuild()` à ajouter à `PelicanApplicationService`)
- `handleCancellation(array $webhookData): void` — lookup serveur par `stripe_subscription_id`, `PelicanApplicationService->suspendServer()`, update statut en DB. **Ne suspend QUE les serveurs avec abo** (`stripe_subscription_id IS NOT NULL`).

### Jobs

- `ProvisionServerJob` : retry 3, backoff exponentiel (10s, 30s, 90s). Appelle `ProvisioningService->provision()`.
- `SuspendServerJob` : retry 3. Appelle `SubscriptionService->handleCancellation()`.

### Installation Stripe

`composer require stripe/stripe-php`. Le Bridge n'utilise PAS la clé API Stripe complète — uniquement la signing secret pour vérifier les webhooks.

### Emails liés au Bridge (à implémenter en même temps)

À ajouter dans `MailTemplateRegistry` + `/admin/email-templates` :

- **"Votre serveur est prêt"** (`ServerReadyMail`) : envoyé par le Bridge en mode local (pas OAuth). Variables : `{server_name}` (nom jeu), `{ip_port}`, `{reset_password_url}`, `{panel_url}`, `{name}`.
- **"Invitation à créer un compte Shop"** (`ShopInvitationMail`) : envoyé depuis l'admin Filament. Variable : `{shop_register_url}`.

Templates EN + FR. Réutiliser le wrapper `resources/views/emails/templated.blade.php`.

### Tests Bridge

- `StripeWebhookTest` : idempotence, user creation, provisioning
- `ProvisioningServiceTest`
- Mocks HTTP (`Http::fake()`) pour Pelican et Stripe

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
