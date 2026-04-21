# Plugins

Peregrine is extensible through a lightweight plugin system: each plugin
ships a small Laravel service provider + an optional React frontend bundle,
and the panel loads them at runtime. Plugins have their own routes, models,
migrations, Filament pages, and frontend UI — without having to fork the
panel itself.

- [Architecture](#architecture)
- [Directory layout](#directory-layout)
- [Plugin manifest](#plugin-manifest)
- [Backend service provider](#backend-service-provider)
- [Frontend bundle](#frontend-bundle)
- [Queue-safe contract](#queue-safe-contract)
- [Publishing to the marketplace](#publishing-to-the-marketplace)

## Architecture

A plugin is a self-contained directory under `plugins/` that exposes:

- A **manifest** (`plugin.json`) describing identity, version, nav entries,
  permissions, and the path to the frontend bundle.
- A **Laravel service provider** — registers routes, migrations, Filament
  pages, event listeners, etc.
- An optional **React frontend bundle** (built with Vite as an IIFE) that
  the panel lazy-loads when the user navigates to a plugin route.

Plugins are **activated per install** from **Admin → Plugins**. Deactivation
at runtime drops the routes from the API surface (they disappear from the
auto-generated OpenAPI docs at `/docs/api` too) and stops serving the
frontend bundle.

## Directory layout

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

## Plugin manifest

`plugin.json` at the plugin root:

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

| Field | Purpose |
|---|---|
| `id` | Stable identifier (slug). Used as the settings key prefix, the directory name, and the route prefix. |
| `name`, `description`, `author`, `license` | Metadata shown in the admin UI + marketplace. |
| `version` | SemVer. Compared against `min_peregrine_version` on install. |
| `service_provider` | Short class name resolved as `Plugins\<StudlyId>\<ServiceProvider>`. |
| `frontend.bundle` | Relative path to the built JS bundle. Served at `/plugins/<id>/bundle.js`. |
| `frontend.server_sidebar_entries` | Injects nav items in the server detail sidebar. Icons are picked from the panel's icon map. |
| `settings_schema` | Admin UI auto-generates a settings form from this schema. Values land in the `settings` table under `plugin.<id>.<key>`. |

## Backend service provider

Minimal shape:

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

The panel's `PluginManager` calls `register()` + `boot()` only when the
plugin is active — no work happens for deactivated plugins.

### Accessing server data with admin-bypass awareness

If your plugin exposes server-scoped routes, use the `Server::scopeAccessibleBy($user)`
scope instead of hand-rolling the pivot check. It automatically honors the
panel's admin bypass (admins see every server without needing a pivot row),
matching the semantics of the core `ServerPolicy`:

```php
Route::get('servers/{serverIdentifier}/my-feature', function (string $id, Request $request) {
    $server = Server::where('identifier', $id)
        ->accessibleBy($request->user())
        ->firstOrFail();

    // ...
});
```

## Frontend bundle

The frontend is built as a **Vite IIFE bundle** that exports a single
`registerPlugin` function. Dependencies shared with the panel (React,
TanStack Query, react-i18next, …) are **not** bundled — they're pulled
from `window.PanelShared`:

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

Build with `vite build --mode plugin` and a minimal `vite.config.ts` that
produces an IIFE:

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

## Queue-safe contract

**Plugin classes must NEVER be serialized into the queue.** Payloads frozen
inside the `jobs` table survive across code changes and end up deserializing
to `__PHP_Incomplete_Class` when the plugin is updated, deactivated, or
refactored.

### Rules

1. **Plugin Mailables must NOT implement `ShouldQueue`.** Mark them `final`
   to prevent accidental regressions.
2. **Plugin Jobs must NOT be queued directly.** Use the core wrapper
   `App\Jobs\SendPluginMail` which takes only scalar arguments and
   reconstructs the Mailable at handle time.

### Dispatching a mail from a plugin

```php
// In a plugin service:
App\Jobs\SendPluginMail::dispatch(
    $user->email,
    Plugins\Invitations\Mail\InvitationMail::class,
    ['invitation_id' => $invitation->id, 'locale' => $user->locale],
);
```

The Mailable `__construct` takes only primitives — reconstructed fresh when
the queue worker picks up the job. No stale plugin class snapshot lingers
in the `jobs` table.

### Activation / deactivation

When an admin toggles a plugin from the Plugins page, the `PluginManager`:

1. Runs the plugin's migrations (up on activate, down on deactivate).
2. Runs `php artisan queue:restart` so queue workers reload their autoload
   map (otherwise they'd still resolve the plugin's classes from before).
3. Purges any jobs in `jobs` + `failed_jobs` that reference the plugin's
   namespace (`App\Services\PluginManager::purgeStaleJobs`).

If you hit a mismatch after an incompatible refactor:

```bash
php artisan plugin:purge-stale-jobs <plugin_id>
```

## Publishing to the marketplace

Admins discover plugins through a **registry JSON** file that Peregrine
fetches at boot:

```
https://raw.githubusercontent.com/<org>/<your-registry-repo>/main/registry.json
```

### Registry entry

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

### Release archive

The `download_url` must point to a `.zip` archive whose root-level folder
name matches the plugin `id`. The archive is what Peregrine fetches when
the admin clicks "Install" in the marketplace.

Structure:

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

### Forking the registry

For a private panel with house plugins, fork the public registry repository
(or create a new one), then set the registry URL from
**Admin → Plugins → Marketplace** (or via the `marketplace_registry_url`
setting). The panel merges multiple registries — core + any override you
configure.

## Related

- [Authentication](authentication.md) — OAuth and 2FA configuration
- [Configuration](configuration.md) — env vars and settings overview
