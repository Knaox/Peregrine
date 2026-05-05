# Minecraft: Modpack — Installer

A Peregrine plugin that lets a server owner install, update and remove a Minecraft modpack on one of their servers in a few clicks. Aggregates the six main public modpack marketplaces, auto-detects the Java version the modpack requires, and switches the server runtime accordingly.

## Capabilities

- **Discovery** — search across **Modrinth**, **CurseForge**, **ATLauncher**, **Feed The Beast**, **Technic** and **VoidsWrath**. The UI adapts to each provider's declared capabilities (some support pagination + filters, some don't).
- **Install / update / remove** — asynchronous, observable from a per-server "Modpacks" tab.
- **Auto Java detection** — once the install script finishes, the plugin fingerprints the resulting server jar via [MCJars](https://versions.mcjars.app/) and picks the matching public Java image (`ghcr.io/pelican-eggs/yolks:java_*`). Falls back to a sensible default on miss.
- **Egg-swap install flow** — uses the Pelican Application API (`PATCH /servers/{id}/startup` + `POST /reinstall`) to swap the server onto a dedicated installer egg, run the bash installer in the install container, then swap back to the original egg with the right Java image. No Wings patch required.
- **Optional integration** with the **Server Invitations** plugin: when present, three sub-user permissions (`modpack.read`, `modpack.install`, `modpack.uninstall`) appear automatically in the permission picker — only on servers whose egg is whitelisted by the admin.

## Admin configuration

Available at `/admin/modpack-settings` (Filament). Three settings:

| Key | Default | Notes |
|---|---|---|
| `modpack_curseforge_api_key` | _(empty)_ | Required to enable the CurseForge provider. Get a key at [console.curseforge.com](https://console.curseforge.com). Stored encrypted. |
| `modpack_whitelisted_egg_ids` | `[]` | Eggs allowed to install modpacks. Until at least one is selected, the "Modpacks" tab stays hidden on every server. |
| `modpack_install_timeout_minutes` | `30` | Beyond this duration without progress, an install is auto-marked failed by the reconciler cron. |
| `modpack_default_provider` | `modrinth` | Provider pre-selected on first page load. |

## Installation lifecycle (high level)

```
[user clicks Install in the modal]
         │
         ▼
POST /api/plugins/minecraft-modpack-installer/servers/{id}/installation
         │
         │ 1. eligibility check + provider configured
         │ 2. snapshot egg/image/startup/env in a `modpack_installations` row
         │ 3. dispatch InstallModpackJob → returns 202
         ▼
[InstallModpackJob — queue]
         │ 4. PATCH /api/application/servers/{id}/startup → installer egg + BB_MODPACK_* env
         │ 5. POST /api/application/servers/{id}/reinstall
         │ 6. dispatch PollInstallStatusJob (delay 15s)
         ▼
[Wings runs the bash installer]
         │ The install script (resources/eggs/peregrine-modpack-installer.sh)
         │ downloads the modpack from the chosen provider, lays it out under
         │ /mnt/server, writes eula.txt, symlinks server.jar.
         ▼
[PollInstallStatusJob — recursive]
         │ polls Pelican every 15s until the server status leaves `installing`
         │ - status = null → success: detect Java via MCJars + swap back to original egg
         │ - status = install_failed → mark failed + best-effort rollback
         │ - elapsed > timeout_minutes → mark failed (timeout)
         ▼
[modpack_installations.status = completed]
         │
         │ The user starts the server normally.
```

The matching uninstall flow is the mirror image, ending with `modpack_installations` row deletion.

## Egg

The plugin owns one Pelican egg, kept as two files for maintainability:

- `resources/eggs/peregrine-modpack-installer.json` — egg template with a `@@INSTALL_SCRIPT@@` placeholder.
- `resources/eggs/peregrine-modpack-installer.sh` — bash installer, written from scratch from each provider's official documentation.

The plugin's `EggImporter` reads both, splices the script into the placeholder, and POSTs the resulting payload to `POST /api/application/eggs/import`. The egg's UUID is `d8a3f1b9-2e4c-4b7a-8f6d-3c9e5d2b1a4f` — Pelican import is keyed by UUID, so re-running the import is a no-op (or an in-place update when the bash file changes).

The egg is imported lazily on the first install request, NOT during plugin activation — this keeps the plugin's `boot()` cheap and avoids hitting Pelican on every Laravel container boot.

## Frontend

Standard Peregrine plugin layout: a single IIFE bundle (`frontend/dist/bundle.js`) committed to the repo, plus its TypeScript sources for review and rebuild. The bundle accesses `window.__PEREGRINE_SHARED__` (React, TanStack Query, react-router-dom, useTranslation) and `window.__PEREGRINE_PLUGINS__` (registers the server sidebar page).

I18n keys live in `frontend/i18n/{en,fr}.json` and are loaded by the core via `GET /api/plugins/minecraft-modpack-installer/i18n/{locale}`.

## Permissions

| Permission | Capability |
|---|---|
| `modpack.read` | Browse modpacks and view the installed one. |
| `modpack.install` | Trigger an install or update. |
| `modpack.uninstall` | Remove the installed modpack. |

Owners of a server have all three implicitly. Sub-users only see the permissions in the picker (and on their server) when the server's egg is whitelisted by the admin.

## Adding a 7th provider

1. Implement `Plugins\MinecraftModpackInstaller\Services\Providers\Contracts\ModpackProviderInterface` in a new class under `src/Services/Providers/`.
2. Add the new case to `Plugins\MinecraftModpackInstaller\Enums\ModpackProvider`.
3. Register the implementation in `MinecraftModpackInstallerServiceProvider::register()`.
4. Add the dispatcher branch in `resources/eggs/peregrine-modpack-installer.sh` (`install_<provider>` function).
5. Add the i18n key `modpacks.providers.<id>.label`.

That's it — no UI change is required: the frontend reads each provider's declared capabilities at runtime and adapts the filter bar accordingly.
