# Plugins

Peregrine est extensible via un système de plugins léger : chaque plugin
embarque un petit service provider Laravel + un bundle frontend React
optionnel, et le panel les charge à l'exécution. Les plugins ont leurs
propres routes, modèles, migrations, pages Filament et UI frontend — sans
qu'il soit nécessaire de forker le panel lui-même.

- [Architecture](#architecture)
- [Arborescence des fichiers](#arborescence-des-fichiers)
- [Manifest du plugin](#manifest-du-plugin)
- [Service provider backend](#service-provider-backend)
- [Bundle frontend](#bundle-frontend)
- [Contrat compatible queue](#contrat-compatible-queue)
- [Publier sur la marketplace](#publier-sur-la-marketplace)

## Architecture

Un plugin est un répertoire autonome sous `plugins/` qui expose :

- Un **manifest** (`plugin.json`) décrivant l'identité, la version, les
  entrées de navigation, les permissions et le chemin du bundle frontend.
- Un **service provider Laravel** — enregistre les routes, migrations,
  pages Filament, listeners d'événements, etc.
- Un **bundle frontend React** optionnel (buildé avec Vite en IIFE) que
  le panel charge à la demande quand l'utilisateur navigue vers une route
  du plugin.

Les plugins sont **activés par installation** depuis **Admin → Plugins**.
La désactivation à l'exécution retire les routes de la surface API (elles
disparaissent aussi de la documentation OpenAPI auto-générée sur
`/docs/api`) et arrête de servir le bundle frontend.

## Arborescence des fichiers

```
plugins/your-plugin/
├── plugin.json                # Manifest (required)
├── icon.svg                   # Shown in the Plugins admin page + marketplace
├── src/
│   ├── YourPluginServiceProvider.php
│   ├── Routes/
│   │   └── api.php            # Autoloaded routes if service provider wires them
│   ├── Migrations/            # Plugin-specific tables
│   ├── Models/
│   ├── Services/
│   ├── Mail/                  # Mailable classes — see queue-safe contract
│   ├── Events/
│   └── Listeners/
├── frontend/
│   ├── index.tsx              # React entry point
│   ├── shared.ts              # Pulls React / TanStack from window.PanelShared
│   ├── dist/
│   │   └── bundle.js          # Built output, shipped with the plugin
│   └── i18n/
│       ├── en.json
│       └── fr.json
└── views/                     # Blade views (emails, Filament pages, …)
```

## Manifest du plugin

`plugin.json` à la racine du plugin :

```json
{
    "id": "invitations",
    "name": "Server Invitations",
    "version": "0.8.1",
    "description": "Invite users to your servers by email with granular permissions.",
    "author": "Peregrine Team",
    "license": "MIT",
    "min_peregrine_version": "1.0.0",
    "service_provider": "InvitationsServiceProvider",
    "frontend": {
        "bundle": "frontend/dist/bundle.js",
        "nav": [],
        "server_sidebar_entries": [
            {
                "id": "users",
                "label_key": "invitations.page.title",
                "icon": "users",
                "route_suffix": "/users",
                "order": 8
            }
        ]
    },
    "settings_schema": [
        {
            "key": "invitation_expiry_days",
            "type": "number",
            "label": "Invitation expiry (days)",
            "default": 7
        }
    ]
}
```

| Champ | Rôle |
|---|---|
| `id` | Identifiant stable (slug). Utilisé comme préfixe de clé dans `settings`, nom du répertoire et préfixe de route. |
| `name`, `description`, `author`, `license` | Métadonnées affichées dans l'UI admin + marketplace. |
| `version` | SemVer. Comparée à `min_peregrine_version` à l'installation. |
| `service_provider` | Nom court de la classe résolue en `Plugins\<StudlyId>\<ServiceProvider>`. |
| `frontend.bundle` | Chemin relatif vers le bundle JS buildé. Servi sur `/plugins/<id>/bundle.js`. |
| `frontend.server_sidebar_entries` | Injecte des items de navigation dans la sidebar du détail serveur. Les icônes sont prises dans la map d'icônes du panel. |
| `settings_schema` | L'UI admin auto-génère un formulaire de paramètres à partir de ce schéma. Les valeurs sont stockées dans la table `settings` sous `plugin.<id>.<key>`. |

## Service provider backend

Forme minimale :

```php
namespace Plugins\Invitations;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class InvitationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../views', 'invitations');

        Route::prefix('api/plugins/invitations')
            ->middleware(['web', 'auth'])
            ->group(__DIR__ . '/Routes/api.php');
    }

    public function register(): void
    {
        $this->app->singleton(Services\InvitationService::class);
    }
}
```

Le `PluginManager` du panel n'appelle `register()` + `boot()` que lorsque
le plugin est actif — aucun travail n'est effectué pour les plugins
désactivés.

### Accéder aux données de serveur en respectant le bypass admin

Si votre plugin expose des routes scopées par serveur, utilisez le scope
`Server::scopeAccessibleBy($user)` plutôt que d'écrire la vérification
pivot à la main. Il honore automatiquement le bypass admin du panel
(les admins voient tous les serveurs sans avoir besoin d'une ligne pivot),
ce qui correspond à la sémantique de la `ServerPolicy` du core :

```php
Route::get('servers/{serverIdentifier}/my-feature', function (string $id, Request $request) {
    $server = Server::where('identifier', $id)
        ->accessibleBy($request->user())
        ->firstOrFail();

    // ...
});
```

## Bundle frontend

Le frontend est buildé sous forme de **bundle Vite IIFE** qui exporte une
unique fonction `registerPlugin`. Les dépendances partagées avec le panel
(React, TanStack Query, react-i18next, …) ne sont **pas** incluses dans
le bundle — elles sont récupérées depuis `window.PanelShared` :

```typescript
// frontend/shared.ts
const shared = window.PanelShared;
export const React = shared.React;
export const useQuery = shared.useQuery;
export const useTranslation = shared.useTranslation;
// ...
```

```typescript
// frontend/index.tsx
import { React, useTranslation } from './shared';

function InvitationsPage() {
    const { t } = useTranslation();
    return React.createElement('div', null, t('invitations.page.title'));
}

window.registerPlugin({
    id: 'invitations',
    routes: [
        { path: '/servers/:id/users', component: InvitationsPage },
    ],
});
```

Build avec `vite build --mode plugin` et un `vite.config.ts` minimal qui
produit un IIFE :

```typescript
export default {
    build: {
        lib: {
            entry: 'frontend/index.tsx',
            formats: ['iife'],
            fileName: () => 'bundle.js',
            name: 'PeregrinePluginInvitations',
        },
        outDir: 'frontend/dist',
        rollupOptions: {
            external: ['react', 'react-dom', '@tanstack/react-query'],
            output: { globals: { react: 'PanelShared.React' } },
        },
    },
};
```

## Contrat compatible queue

**Les classes de plugin ne doivent JAMAIS être sérialisées dans la queue.**
Les payloads figés dans la table `jobs` survivent aux changements de code
et finissent désérialisés en `__PHP_Incomplete_Class` quand le plugin est
mis à jour, désactivé ou refactoré.

### Règles

1. **Les Mailables de plugin ne doivent PAS implémenter `ShouldQueue`.**
   Marquez-les `final` pour prévenir toute régression accidentelle.
2. **Les Jobs de plugin ne doivent PAS être mis en queue directement.**
   Utilisez le wrapper du core `App\Jobs\SendPluginMail` qui ne prend que
   des arguments scalaires et reconstruit le Mailable au moment du handle.

### Dispatcher un mail depuis un plugin

```php
// In a plugin service:
App\Jobs\SendPluginMail::dispatch(
    $user->email,
    Plugins\Invitations\Mail\InvitationMail::class,
    ['invitation_id' => $invitation->id, 'locale' => $user->locale],
);
```

Le `__construct` du Mailable ne prend que des primitives — il est
reconstruit à neuf quand le worker queue traite le job. Aucun snapshot
obsolète de classe de plugin ne reste dans la table `jobs`.

### Activation / désactivation

Quand un admin bascule un plugin depuis la page Plugins, le `PluginManager` :

1. Lance les migrations du plugin (up à l'activation, down à la désactivation).
2. Lance `php artisan queue:restart` pour que les workers queue rechargent
   leur map d'autoload (sinon ils continueraient à résoudre les classes
   du plugin telles qu'elles étaient avant).
3. Purge tous les jobs dans `jobs` + `failed_jobs` qui référencent le
   namespace du plugin (`App\Services\PluginManager::purgeStaleJobs`).

Si vous tombez sur une incompatibilité après un refactor incompatible :

```bash
php artisan plugin:purge-stale-jobs <plugin_id>
```

## Publier sur la marketplace

Les admins découvrent les plugins via un fichier **registre JSON** que
Peregrine récupère au démarrage :

```
https://raw.githubusercontent.com/<org>/<your-registry-repo>/main/registry.json
```

### Entrée de registre

```json
{
    "plugins": [
        {
            "id": "invitations",
            "name": "Server Invitations",
            "version": "0.8.1",
            "description": "…",
            "author": "Peregrine Team",
            "license": "MIT",
            "min_peregrine_version": "1.0.0",
            "download_url": "https://github.com/<org>/<your-plugin-repo>/releases/download/v0.8.1/invitations-0.8.1.zip",
            "homepage": "https://github.com/<org>/<your-plugin-repo>",
            "tags": ["users", "permissions"]
        }
    ]
}
```

### Archive de release

Le `download_url` doit pointer vers une archive `.zip` dont le dossier
racine porte le même nom que l'`id` du plugin. C'est cette archive que
Peregrine récupère quand l'admin clique sur "Install" dans la marketplace.

Structure :

```
invitations-0.8.1.zip
└── invitations/
    ├── plugin.json
    ├── icon.svg
    ├── src/
    ├── frontend/
    │   └── dist/bundle.js       # Pre-built — the panel doesn't run npm
    └── views/
```

### Forker le registre

Pour un panel privé avec des plugins maison, forkez le dépôt public du
registre (ou créez-en un nouveau), puis renseignez l'URL du registre
depuis **Admin → Plugins → Marketplace** (ou via la setting
`marketplace_registry_url`). Le panel fusionne plusieurs registres —
le core + tout override que vous configurez.

## Liens connexes

- [Authentication](authentication.md) — configuration OAuth et 2FA
- [Configuration](configuration.md) — vue d'ensemble des variables d'env et settings
