<div align="center">
  <img src="https://raw.githubusercontent.com/Knaox/Peregrine/main/public/images/logo.svg" width="140" alt="Peregrine" />

  <h1>Peregrine</h1>

  <p>
    <strong>Open-source game-server management panel powered by <a href="https://pelican.dev">Pelican</a>.</strong><br>
    Full-featured player & admin panel, WebSocket live stats, granular permissions, plugin marketplace.
  </p>

  <p>
    <a href="https://github.com/Knaox/Peregrine/releases"><img alt="Version" src="https://img.shields.io/github/v/release/Knaox/Peregrine?include_prereleases&label=version&color=e11d48"></a>
    <a href="https://github.com/Knaox/Peregrine/blob/main/LICENSE"><img alt="License" src="https://img.shields.io/badge/license-MIT-blue"></a>
    <a href="https://github.com/Knaox/Peregrine/actions/workflows/docker.yml"><img alt="Docker build" src="https://github.com/Knaox/Peregrine/actions/workflows/docker.yml/badge.svg"></a>
    <img alt="PHP 8.3" src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white">
    <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white">
    <img alt="Filament 5" src="https://img.shields.io/badge/Filament-5-F59E0B?logo=livewire&logoColor=white">
    <img alt="React 19" src="https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black">
    <img alt="Tailwind 4" src="https://img.shields.io/badge/Tailwind-4-38BDF8?logo=tailwindcss&logoColor=white">
  </p>

  <p>
    <a href="#quick-start">Quick start</a> ·
    <a href="#features">Features</a> ·
    <a href="#configuration">Configuration</a> ·
    <a href="#updates">Updates</a> ·
    <a href="#plugins">Plugins</a> ·
    <a href="#contributing">Contributing</a>
  </p>
</div>

---

## What is Peregrine?

**Peregrine** is a modern, open-source control panel for game servers. It speaks to [Pelican](https://pelican.dev) (a fork of Pterodactyl) over its Application and Client APIs, and adds everything a production hoster needs on top: a slick React SPA for players, a Filament admin panel, a theme editor, a plugin system with a marketplace, WebSocket-driven live stats, and Docker deployment.

It works **standalone** (local auth, manual user/server management) or as part of a larger **SaaS stack** (OAuth2 SSO + Stripe webhook bridge for automatic provisioning).

---

## Features

### Player panel
- **Server overview** — live CPU / RAM / disk / network via Wings WebSocket, uptime, banner image, quick actions.
- **Console** — xterm.js terminal, command history (per-user, persisted), start / stop / restart / kill with granular permissions.
- **File manager** — full Pelican parity: list, read, edit, write, rename, delete, copy, compress, decompress, `chmod` (octal mask), remote URL `pull`, drag-and-drop upload, directory creation, bulk actions.
- **SFTP** — credentials, clipboard copy, separate SFTP password reset.
- **Databases** — create, rotate password, delete, view credentials (permission-gated).
- **Backups** — create, download, lock, restore, delete.
- **Schedules** — create, edit, run-now, cron presets + advanced editor, task management.
- **Network** — allocations list, notes, primary, bulk delete, add.
- **Invitations** (default plugin) — invite users by email with granular Pelican permissions, edit permissions of active subusers and pending invitations, self-protection, email flow with queue-safe dispatch.

### Admin panel (Filament 5)
- Users, Servers, Plans, Eggs, Nests, Nodes — full CRUD + Pelican sync.
- **Settings** — app name, logo, favicon, custom header links, Pelican credentials, auth mode, bridge.
- **Theme editor** — 15+ color tokens (primary/accent/danger/…), typography, border radius, card style, sidebar layout, widget order, custom CSS injection.
- **Plugins** — installed + marketplace tabs, install / activate / deactivate / update / uninstall, per-plugin settings schema.
- **Email templates** — per-locale subject + HTML body, with variable placeholders (`{server_name}`, `{accept_url}`, `{app_name}`, …).
- **About & Updates** — live GitHub release check, Docker-aware update commands with one-click clipboard copy.

### Platform
- **Bilingual** EN + FR (source of truth JSON files) — every new key **must** land in both.
- **Granular permissions** — every Pelican subuser permission key is mapped to a dedicated policy ability. Frontend hides controls users can't use; backend returns 403 if they try anyway.
- **Redis caching** — branding, theme, allocations, SFTP credentials, backup/database/schedule lists, settings.
- **Queue-safe** — plugin-defined Mailables never get serialised into the queue (see `app/Jobs/SendPluginMail.php`).
- **Docker-first** — multi-stage build, `docker compose up -d`, automatic image publication to `ghcr.io/knaox/peregrine:latest`.
- **Auto-update checker** — Admin → About tells you when a new GitHub release is out and gives you the exact commands.

---

## Quick start

### Docker (recommended)

```bash
git clone https://github.com/Knaox/Peregrine.git
cd Peregrine
docker compose up -d
```

Open **http://localhost:8080** in your browser — the Setup Wizard guides you through 7 steps (language, database, admin account, Pelican credentials, auth mode, optional Bridge, summary). Docker is detected automatically and DB host is pre-filled.

### Or pull the published image directly

```bash
docker run -d \
  --name peregrine \
  -p 8080:8080 \
  -v peregrine-storage:/var/www/html/storage \
  ghcr.io/knaox/peregrine:latest
```

### Bare metal

```bash
git clone https://github.com/Knaox/Peregrine.git
cd Peregrine

composer install
pnpm install
pnpm run build

cp .env.example .env
php artisan key:generate
php artisan storage:link

# Start your own PHP server / queue worker / reverse proxy — then open the URL
php artisan serve
php artisan queue:work       # in a second terminal
```

Then hit the URL and run the Setup Wizard.

---

## Configuration

Almost everything is editable at runtime via **Admin → Settings** and **Admin → Appearance**. The `.env` holds only what has to boot before Laravel does:

```env
APP_NAME=Peregrine
APP_VERSION=1.0.0-alpha.1
APP_URL=https://games.example.com
PANEL_INSTALLED=true

# Auth (mode is picked in the Setup Wizard)
AUTH_MODE=local                # or "oauth"
OAUTH_CLIENT_ID=
OAUTH_CLIENT_SECRET=
OAUTH_AUTHORIZE_URL=
OAUTH_TOKEN_URL=
OAUTH_USER_URL=

# Pelican
PELICAN_URL=https://pelican.example.com
PELICAN_ADMIN_API_KEY=         # never exposed to the browser
PELICAN_CLIENT_API_KEY=

# Mail
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=

# Bridge (optional — Stripe webhook auto-provisioning)
BRIDGE_ENABLED=false
STRIPE_WEBHOOK_SECRET=

# Marketplace
MARKETPLACE_REGISTRY_URL=https://raw.githubusercontent.com/Knaox/peregrine-plugins/main/registry.json
UPDATE_REPO=Knaox/Peregrine
```

---

## Updates

Admin → **About & Updates** shows the installed version, checks GitHub for the latest panel release (plugin releases are filtered out), and hands you the exact commands with clipboard buttons.

### Docker

```bash
docker compose pull && docker compose up -d
docker compose exec app php artisan migrate --force
```

### Bare metal

```bash
git pull
composer install --no-dev --optimize-autoloader && pnpm install
pnpm run build
php artisan migrate --force && php artisan config:cache && php artisan queue:restart
```

---

## Plugins

Peregrine ships with **Server Invitations** activated by default (subuser management with granular permissions, self-protection, queue-safe email dispatch).

Browse more from **Admin → Plugins → Marketplace**, or install from the CLI:

```bash
php artisan plugin:install <plugin-id>
php artisan plugin:activate <plugin-id>
```

The marketplace registry lives at `Knaox/peregrine-plugins` on GitHub. Override with `MARKETPLACE_REGISTRY_URL` to host your own.

### Build your own plugin

```bash
php artisan make:plugin my-plugin
```

Drops a scaffolded plugin under `plugins/my-plugin/` with a service provider, manifest, migrations folder, and a React frontend entry point.

---

## Docker image

Built and published on every push to `main`. Multi-arch (`linux/amd64` + `linux/arm64`).

| Tag | Produced by |
|---|---|
| `ghcr.io/knaox/peregrine:latest` | push to `main` |
| `ghcr.io/knaox/peregrine:main-<sha>` | push to `main` |
| `ghcr.io/knaox/peregrine:1.2.3` / `:1.2` / `:1` | `v*.*.*` tag |

GitHub Actions workflow: [`.github/workflows/docker.yml`](.github/workflows/docker.yml).

---

## Tech stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.3 · Laravel 13 · Filament 5 (Livewire 4) |
| Frontend | React 19 · TypeScript · Vite 6 · Tailwind 4 · TanStack Query · React Router 7 · Motion |
| Database | MySQL 8 |
| Cache / Queue | Redis (database fallback) |
| Real-time | Wings WebSocket + xterm.js |
| Container | Docker multi-stage · GHCR |

---

## Contributing

Peregrine is early-stage open source. Issues, PRs, and plugin submissions are welcome.

Local dev:

```bash
composer install
pnpm install
pnpm run dev            # Vite HMR on 5173
php artisan serve       # PHP on 8000
php artisan queue:work  # for emails / sync jobs
```

Conventions (enforced by `CLAUDE.md` + code review):

- TypeScript strict. No `any`. Files ≤ 300 lines (split if they grow).
- i18n: **every** user-facing string goes through i18n keys. Update `en.json` **and** `fr.json` in the same commit.
- Backend: thin controllers, logic in Services, validation in Form Requests, responses via API Resources, auth via Policies.
- Commits: conventional (`feat:`, `fix:`, `refactor:`, …).

---

## Security

If you find a vulnerability, please **do not open a public issue**. Email the maintainer at `damienrouge@hotmail.com` and allow reasonable time for a fix before disclosure.

---

## License

[MIT](LICENSE). You're free to self-host, fork, and modify.

Peregrine is an independent project and is **not affiliated with Pelican, Pterodactyl, or Laravel**.

---

## Credits

Built on top of [Pelican](https://pelican.dev), [Laravel](https://laravel.com), [Filament](https://filamentphp.com), [React](https://react.dev), [Tailwind](https://tailwindcss.com), and the open-source community that makes all of this possible.
