<div align="center">
  <img src="https://raw.githubusercontent.com/Knaox/Peregrine/main/public/images/logo.webp" width="360" alt="Peregrine" />

  <h3>Open-source game-server management panel powered by <a href="https://pelican.dev">Pelican</a>.</h3>
  <p>Beautiful player interface · full admin panel · granular permissions · plugin marketplace · Docker-first.</p>

  <p>
    <a href="https://github.com/Knaox/Peregrine/releases"><img alt="Version" src="https://img.shields.io/github/v/release/Knaox/Peregrine?include_prereleases&label=version&color=e11d48"></a>
    <a href="https://github.com/Knaox/Peregrine/blob/main/LICENSE"><img alt="License" src="https://img.shields.io/badge/license-MIT-blue"></a>
    <a href="https://github.com/Knaox/Peregrine/actions/workflows/docker.yml"><img alt="Docker build" src="https://github.com/Knaox/Peregrine/actions/workflows/docker.yml/badge.svg"></a>
    <a href="https://github.com/Knaox/Peregrine/pkgs/container/peregrine"><img alt="Image" src="https://img.shields.io/badge/image-ghcr.io%2Fknaox%2Fperegrine-2496ED?logo=docker&logoColor=white"></a>
    <img alt="PHP 8.3" src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white">
    <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white">
    <img alt="Filament 5" src="https://img.shields.io/badge/Filament-5-F59E0B?logo=livewire&logoColor=white">
    <img alt="React 19" src="https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black">
  </p>

  <p>
    <a href="#-install-in-60-seconds">Install in 60 s</a> ·
    <a href="#-features">Features</a> ·
    <a href="#-copy-paste-docker-compose">Compose files</a> ·
    <a href="#-updates">Updates</a> ·
    <a href="#-plugins">Plugins</a> ·
    <a href="#-developing-locally">Developing</a> ·
    <a href="#license">License</a>
  </p>
</div>

---

## 🦅 What is Peregrine?

**Peregrine** is a modern, open-source control panel for game servers. It speaks to [Pelican](https://pelican.dev) (the fork of Pterodactyl) over its Application and Client APIs, and wraps it in everything a production hoster actually needs:

- a slick React SPA for players (overview, WebSocket console, file manager, SFTP, databases, backups, schedules, network, invitations),
- a full Filament 5 admin panel (users, servers, plans, eggs, nests, nodes, theme editor, plugin marketplace, email templates, about/updates),
- a strict permission system wired end-to-end (if you don't have the right, the button isn't there — and the backend 403s anyway),
- a plugin architecture with a GitHub-hosted marketplace registry,
- a Docker-first deployment (published to `ghcr.io/knaox/peregrine` on every push to `main`),
- bilingual French / English UI.

It runs **standalone** (local auth) or as part of a larger **SaaS stack** (OAuth2 SSO + Stripe webhook bridge).

---

## ⚡ Install in 60 seconds

> **The heavy lifting is done in your browser.** After you start the container, open port 8080 and a 7-step **Setup Wizard** walks you through language, database, admin account, Pelican credentials, auth mode, optional Bridge, and summary. You never touch `.env` manually.

### Option A — one container, SQLite bundled (recommended for first try)

```bash
docker run -d --name peregrine \
  -p 8080:8080 \
  -v peregrine_storage:/var/www/html/storage \
  -v peregrine_plugins:/var/www/html/plugins \
  ghcr.io/knaox/peregrine:latest
```

Open `http://localhost:8080` and run the Setup Wizard.

### Option B — the official `docker-compose.yml`

Save this as `docker-compose.yml`, run `docker compose up -d`. Also works as a **Portainer Stack** — paste and Deploy.

```yaml
services:
  peregrine:
    image: ghcr.io/knaox/peregrine:latest
    container_name: peregrine
    restart: unless-stopped
    ports:
      - "8080:8080"
    environment:
      APP_URL: http://localhost:8080
      DOCKER: "true"
    volumes:
      - peregrine_storage:/var/www/html/storage
      - peregrine_plugins:/var/www/html/plugins

volumes:
  peregrine_storage:
  peregrine_plugins:
```

### Option C — all-in-one stack (app + MySQL + Redis)

Production-grade bundle. Turnkey — Portainer-paste-ready. Redis for cache/queue/session, MySQL 8.4 for durability.

```yaml
services:
  peregrine:
    image: ghcr.io/knaox/peregrine:latest
    container_name: peregrine
    restart: unless-stopped
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started
    ports:
      - "8080:8080"
    environment:
      APP_URL: http://localhost:8080
      DOCKER: "true"
      DB_CONNECTION: mysql
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: peregrine
      DB_USERNAME: peregrine
      DB_PASSWORD: change-me
      CACHE_STORE: redis
      QUEUE_CONNECTION: redis
      SESSION_DRIVER: redis
      REDIS_HOST: redis
    volumes:
      - peregrine_storage:/var/www/html/storage
      - peregrine_plugins:/var/www/html/plugins

  mysql:
    image: mysql:8.4
    container_name: peregrine-mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: peregrine
      MYSQL_USER: peregrine
      MYSQL_PASSWORD: change-me
      MYSQL_ROOT_PASSWORD: change-me-too
    volumes:
      - peregrine_mysql:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 15s
      retries: 8

  redis:
    image: redis:7-alpine
    container_name: peregrine-redis
    restart: unless-stopped
    command: ["redis-server", "--appendonly", "yes"]
    volumes:
      - peregrine_redis:/data

volumes:
  peregrine_storage:
  peregrine_plugins:
  peregrine_mysql:
  peregrine_redis:
```

In the Setup Wizard, fill the **Database** step with:

| Field | Value |
|---|---|
| Host | `mysql` |
| Port | `3306` |
| Database | `peregrine` |
| User | `peregrine` |
| Password | *the one from `DB_PASSWORD`* |

Both compose files are shipped in the repo: [`docker-compose.yml`](docker-compose.yml) + [`docker-compose.full.yml`](docker-compose.full.yml).

### Option D — bare metal (no Docker)

```bash
git clone https://github.com/Knaox/Peregrine.git
cd Peregrine

composer install --no-dev --optimize-autoloader
pnpm install
pnpm run build

cp .env.example .env
php artisan key:generate
php artisan storage:link

php artisan serve &                    # HTTP on :8000
php artisan queue:work --daemon &      # mail + sync jobs
```

Reverse-proxy with nginx / Caddy / Traefik as you normally would.

---

## ✨ Features

### Player panel (React 19 SPA)
- **Overview** — live CPU / RAM / disk / network via Wings WebSocket, uptime, banner image, quick actions gated by permissions.
- **Console** — xterm.js terminal, per-user persistent command history, Start / Stop / Restart / Kill with granular `control.*` gating.
- **File manager** — **full Pelican parity**: list, read, edit, write, rename, delete, copy, compress, decompress, `chmod` (octal), remote URL `pull`, drag-and-drop upload, folder creation, bulk actions. Read-only mode for users without `file.update`.
- **SFTP** — credentials panel, clipboard copy, separate SFTP password reset — gated by `file.sftp`.
- **Databases** — create, rotate password, delete, view credentials (each action gated individually).
- **Backups** — create, download, lock, restore, delete.
- **Schedules** — create, edit, run-now, cron presets + advanced editor, task management.
- **Network** — allocations list, notes, primary, bulk delete, add.
- **Invitations** (shipped plugin) — invite users by email with granular permissions, edit permissions of active subusers and pending invitations, self-protection, queue-safe email dispatch.

### Admin panel (Filament 5)
- Resources: Users, Servers, Plans, Eggs, Nests, Nodes — with one-click Pelican sync.
- **Appearance** — theme editor with 15+ color tokens, border radius, font, header customization, custom CSS injection. The admin panel respects your theme too.
- **Settings** — app name, logo, favicon, custom header links, Pelican credentials, auth mode, bridge.
- **Plugins** — installed + marketplace tabs; install / activate / deactivate / update / uninstall; per-plugin settings schema.
- **Email templates** — per-locale subject + HTML body with variable placeholders (`{server_name}`, `{accept_url}`, `{app_name}`, …). Emails automatically use your uploaded favicon as the logo.
- **About & Updates** — live GitHub release check; Docker-aware update commands with one-click clipboard copy.

### Platform
- **Strict permissions** — every Pelican subuser permission key maps to a dedicated policy ability. Frontend hides controls users can't use; backend returns 403 if they try the API directly.
- **Redis caching** — branding, theme, allocations, SFTP credentials, backup/database/schedule lists, settings.
- **Queue-safe** — plugin-defined Mailables never get serialised into the queue (see `app/Jobs/SendPluginMail.php`).
- **Bilingual EN + FR** — every new string lands in both i18n files.
- **Docker-first** — multi-arch image (`linux/amd64` + `linux/arm64`) auto-built on every push to `main`.
- **Auto-update checker** — Admin → About tells you when a new GitHub release is out and gives you the exact commands.

---

## 🧾 Configuration

Almost everything is editable at runtime via **Admin → Settings** and **Admin → Appearance**. The `.env` only holds what Laravel needs before it can boot.

### Minimum env vars (the Setup Wizard writes these for you)

```env
APP_NAME=Peregrine
APP_URL=https://games.example.com
PANEL_INSTALLED=true

DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=peregrine
DB_USERNAME=peregrine
DB_PASSWORD=change-me

PELICAN_URL=https://pelican.example.com
PELICAN_ADMIN_API_KEY=         # admin API key — NEVER exposed to browsers
PELICAN_CLIENT_API_KEY=        # client API key — proxied through Peregrine

AUTH_MODE=local                # or "oauth"
MAIL_MAILER=smtp
```

### Optional env vars

```env
# OAuth SSO
OAUTH_CLIENT_ID=
OAUTH_CLIENT_SECRET=
OAUTH_AUTHORIZE_URL=
OAUTH_TOKEN_URL=
OAUTH_USER_URL=

# Stripe webhook → auto-provisioning
BRIDGE_ENABLED=false
STRIPE_WEBHOOK_SECRET=

# Marketplace + updates
MARKETPLACE_REGISTRY_URL=https://raw.githubusercontent.com/Knaox/peregrine-plugins/main/registry.json
UPDATE_REPO=Knaox/Peregrine
```

See [`.env.example`](.env.example) for the full list.

---

## 🔄 Updates

Admin → **About & Updates** shows the installed version, checks GitHub for the latest panel release (plugin releases are filtered out), and hands you the exact commands with clipboard buttons — Docker-aware.

### Docker

```bash
docker compose pull && docker compose up -d
```

Migrations run automatically on container start (the entrypoint handles it when `PANEL_INSTALLED=true`).

### Bare metal

```bash
git pull
composer install --no-dev --optimize-autoloader && pnpm install
pnpm run build
php artisan migrate --force && php artisan config:cache && php artisan queue:restart
```

---

## 🧩 Plugins

Peregrine ships with **Server Invitations** activated by default (subuser management with granular permissions, self-protection, queue-safe email dispatch).

Browse more from **Admin → Plugins → Marketplace**, or install from the CLI:

```bash
php artisan plugin:install <plugin-id>
php artisan plugin:activate <plugin-id>
```

The marketplace registry lives at `Knaox/peregrine-plugins` on GitHub. Override with `MARKETPLACE_REGISTRY_URL` to run a private registry.

### Build your own plugin

```bash
php artisan make:plugin my-plugin
```

Scaffolds `plugins/my-plugin/` with a service provider, manifest, migrations folder, and a React frontend entry point. See `plugins/invitations/` for the reference implementation.

---

## 🐳 Docker image tags

Published on every push to `main` and on every panel version tag. Multi-arch (`linux/amd64` + `linux/arm64`).

| Tag | Produced by |
|---|---|
| `ghcr.io/knaox/peregrine:latest` | push to `main` |
| `ghcr.io/knaox/peregrine:main-<sha>` | push to `main` |
| `ghcr.io/knaox/peregrine:1.2.3` / `:1.2` / `:1` | `v*.*.*` tag |

Workflow: [`.github/workflows/docker.yml`](.github/workflows/docker.yml).

---

## 🧱 Tech stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.3 · Laravel 13 · Filament 5 (Livewire 4) |
| Frontend | React 19 · TypeScript · Vite 6 · Tailwind 4 · TanStack Query · React Router 7 · Motion |
| Database | MySQL 8 (SQLite supported) |
| Cache / Queue | Redis (database fallback) |
| Real-time | Wings WebSocket + xterm.js |
| Container | Docker multi-stage · GHCR · nginx + php-fpm + supervisord |

---

## 🛠 Developing locally

```bash
composer install
pnpm install
pnpm run dev            # Vite HMR on :5173
php artisan serve       # PHP on :8000
php artisan queue:work  # emails + sync jobs
```

Conventions (enforced by code review):

- **TypeScript strict**, no `any`, files ≤ 300 lines.
- **i18n**: every user-facing string goes through i18n keys. Update `en.json` and `fr.json` in the same commit.
- **Thin controllers**: logic in Services, validation in Form Requests, responses via API Resources, auth via Policies.
- **Commits**: conventional (`feat:`, `fix:`, `refactor:` …).

---

## 🔒 Security

If you find a vulnerability, please **do not open a public issue**. Email the maintainer at `damienrouge@hotmail.com` and allow reasonable time for a fix before disclosure.

---

## License

[MIT](LICENSE). Self-host freely, fork, modify, resell — no strings.

Peregrine is an independent project and is **not affiliated with Pelican, Pterodactyl, or Laravel**.

---

## Credits

Built on top of [Pelican](https://pelican.dev), [Laravel](https://laravel.com), [Filament](https://filamentphp.com), [React](https://react.dev), [Tailwind](https://tailwindcss.com), and the open-source community that makes all of this possible.
