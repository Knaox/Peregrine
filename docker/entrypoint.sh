#!/bin/sh
set -e

APP_ROOT=/var/www/html
cd "$APP_ROOT"

# -------------------------------------------------------------------
# Strip empty env vars — Docker compose sends the var even when the
# shell expansion ${VAR:-} is empty, and phpdotenv runs immutable:
# it sees "DB_HOST is already set (to empty)" and REFUSES to load the
# real value from .env. Result: the wizard completes, .env is correct,
# but requests still try `mysql:host=;port=3306` because php-fpm only
# sees the empty Docker-provided value.
# Unsetting empty vars lets the .env values take over as intended.
# -------------------------------------------------------------------
for var in APP_URL APP_NAME TRUSTED_PROXIES PANEL_INSTALLED \
           DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
           REDIS_HOST REDIS_PORT REDIS_PASSWORD \
           CACHE_STORE QUEUE_CONNECTION SESSION_DRIVER \
           MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD \
           MAIL_FROM_ADDRESS MAIL_FROM_NAME \
           PELICAN_URL PELICAN_ADMIN_API_KEY PELICAN_CLIENT_API_KEY \
           OAUTH_CLIENT_ID OAUTH_CLIENT_SECRET OAUTH_AUTHORIZE_URL \
           OAUTH_TOKEN_URL OAUTH_USER_URL \
           STRIPE_WEBHOOK_SECRET \
           BROADCAST_CONNECTION REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET \
           REVERB_HOST REVERB_PORT REVERB_SCHEME REVERB_SERVER_HOST REVERB_SERVER_PORT; do
    eval "val=\"\${$var:-}\""
    if [ -z "$val" ]; then
        unset "$var"
    fi
done

# Persistent dirs that must exist on a fresh named volume.
mkdir -p storage/app/public/branding \
         storage/app/plugin-uploads \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/database \
         bootstrap/cache \
         plugins

# -------------------------------------------------------------------
# Plugin upload writability — the named volume `peregrine_plugins`
# can reset ownership on first mount (Docker copies the container
# layer, but if the volume already exists from an older image it
# keeps its previous perms). Re-asserting on every boot guarantees
# admin-uploaded plugins can land in plugins/ regardless of history.
# -------------------------------------------------------------------
chown -R www-data:www-data plugins storage/app/plugin-uploads 2>/dev/null || true
chmod -R u+rwX,g+rwX plugins storage/app/plugin-uploads 2>/dev/null || true

# -------------------------------------------------------------------
# Persistent .env — survives container restarts (lives in storage/).
#
# The Setup Wizard writes PANEL_INSTALLED=true + DB/Pelican/auth values
# here; without a persistent path, every `docker compose up -d` would
# revert the panel to "not installed" and force the wizard to run again.
#
# Layout: real file at storage/.env, symlink at /var/www/html/.env.
# -------------------------------------------------------------------
if [ ! -f storage/.env ]; then
    if [ -f .env ] && [ ! -L .env ]; then
        # Legacy install with a real .env at the repo root — migrate it.
        mv .env storage/.env
    else
        touch storage/.env
    fi
fi

# Make sure /var/www/html/.env is a symlink to storage/.env on every boot
# (even if the image ships its own default that Docker copied over).
if [ ! -L .env ] || [ "$(readlink .env)" != "storage/.env" ]; then
    rm -f .env
    ln -s storage/.env .env
fi

# Generate APP_KEY on first boot if the operator didn't set one.
if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=' storage/.env; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    echo "APP_KEY=${APP_KEY}" >> storage/.env
    export APP_KEY
fi

# -------------------------------------------------------------------
# Reverb auto-config on first boot.
#
# The image ships supervisord with `[program:reverb]` already running
# `php artisan reverb:start` on 127.0.0.1:6001 (fronted by nginx
# /app/ + /apps/ proxies on 8080). For that daemon to be reachable
# from a browser the Laravel side needs four things in .env :
#
#   1. BROADCAST_CONNECTION=reverb (otherwise events go to the `null`
#      / `log` driver and are silently dropped).
#   2. REVERB_APP_ID / KEY / SECRET — the Pusher-protocol triplet
#      that Echo uses to authenticate. Random per-deployment, must
#      match between server and frontend.
#   3. REVERB_HOST — the public hostname the browser dials. Derived
#      here from APP_URL so a single APP_URL change updates both.
#   4. REVERB_PORT / SCHEME — defaulted to 443/https because the
#      production deploy is always behind the in-image nginx (which
#      handles SSL termination via the operator's reverse proxy /
#      Cloudflare). Operators on plaintext localhost can override.
#
# Everything is appended to storage/.env once and survives across
# container restarts (the .env is symlinked from storage/.env, see
# block above). Subsequent boots no-op because the keys already
# exist. This is the same one-shot pattern as APP_KEY above.
# -------------------------------------------------------------------
if ! grep -qE '^BROADCAST_CONNECTION=' storage/.env; then
    echo "BROADCAST_CONNECTION=reverb" >> storage/.env
    export BROADCAST_CONNECTION=reverb
fi

if ! grep -qE '^REVERB_APP_ID=' storage/.env; then
    REVERB_APP_ID="$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')"
    REVERB_APP_KEY="$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')"
    REVERB_APP_SECRET="$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')"
    {
        echo "REVERB_APP_ID=${REVERB_APP_ID}"
        echo "REVERB_APP_KEY=${REVERB_APP_KEY}"
        echo "REVERB_APP_SECRET=${REVERB_APP_SECRET}"
    } >> storage/.env
    export REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET
fi

# REVERB_HOST defaults to APP_URL's hostname so the browser dials the
# same domain that serves the panel — Cloudflare's WebSocket support
# on /app/ then routes back to nginx which proxies to 127.0.0.1:6001.
# Falls through to a plain APP_URL strip when `python3` / `awk`
# aren't available (busy-box / alpine / scratch).
if ! grep -qE '^REVERB_HOST=' storage/.env; then
    APP_HOST=""
    if [ -n "${APP_URL:-}" ]; then
        APP_HOST="$(echo "$APP_URL" | sed -E 's#^https?://##; s#/.*##; s#:.*##')"
    fi
    if [ -n "$APP_HOST" ]; then
        echo "REVERB_HOST=${APP_HOST}" >> storage/.env
        export REVERB_HOST="$APP_HOST"
    fi
fi

# Public-facing port + scheme : 443/https is what the in-image nginx
# fronting + a typical Cloudflare / Caddy / Traefik reverse proxy
# delivers. Operators on a non-TLS local stack can override either
# value via docker compose env or by editing storage/.env.
if ! grep -qE '^REVERB_PORT=' storage/.env; then
    echo "REVERB_PORT=443" >> storage/.env
    export REVERB_PORT=443
fi
if ! grep -qE '^REVERB_SCHEME=' storage/.env; then
    echo "REVERB_SCHEME=https" >> storage/.env
    export REVERB_SCHEME=https
fi

# SQLite default path — create the file if it doesn't exist yet.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f storage/database/database.sqlite ]; then
    touch storage/database/database.sqlite
fi

# Ensure writable perms (volumes from Portainer start as root-owned).
# The Setup Wizard writes .env from the www-data-owned PHP worker, so
# both the file and its parent directory need to be www-data-writable.
#
# public/plugins is where PluginLifecycle::createPublicSymlink() creates
# symlinks to each active plugin's frontend/dist — without www-data
# ownership, activating a plugin fails with "mkdir(): Permission denied".
mkdir -p public/plugins 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache plugins public/plugins 2>/dev/null || true
chown www-data:www-data storage/.env .env 2>/dev/null || true

# Honour explicit PANEL_INSTALLED=false override (operator wants to re-run
# the wizard): remove the sentinel so EnsureInstalled bounces to /setup.
if [ "${PANEL_INSTALLED:-}" = "false" ]; then
    rm -f storage/.installed
fi

# Mirror a PANEL_INSTALLED=true in storage/.env into the sentinel so operators
# who edit the persistent .env don't need to touch an extra file.
if grep -qE '^PANEL_INSTALLED=true' storage/.env 2>/dev/null; then
    : > storage/.installed
fi

# Run migrations + warm caches when the panel is marked installed.
if [ "${PANEL_INSTALLED:-false}" = "true" ] || [ -f storage/.installed ]; then
    php artisan migrate --force || true

    # Plugin reconciliation BEFORE caches warm — this is what prevents the
    # 2026-05-08 prod incident from recurring : after a `git pull` (or a
    # marketplace install on an old base), a plugin's on-disk version can
    # diverge from its DB row, leaving a hot row pointing at a manifest
    # that doesn't actually exist on disk OR pointing at an outdated DB
    # state that's incompatible with newer host code (e.g. `invitations`
    # 1.0.0 in DB while disk is at 1.1.0 with new public API). The
    # `plugin:reconcile-on-boot` command :
    #   1. Deactivates rows whose directory has vanished (zombie rows
    #      from a previously-bundled plugin no longer in the image).
    #   2. force-resyncs rows whose disk version != DB version, running
    #      any new migrations the upgraded plugin shipped.
    # The command is idempotent and never throws fatally — broken plugins
    # are gated downstream by `PluginBootstrap`'s defensive boot.
    php artisan plugin:reconcile-on-boot || true

    php artisan config:cache || true
    php artisan route:cache || true
fi

# storage:link is idempotent; ignore if target exists.
php artisan storage:link 2>/dev/null || true

# Recreate public/plugins/{id} symlinks for every active plugin. Necessary
# because public/plugins/ lives in the container's ephemeral FS — at every
# image redeploy the symlinks are gone, plugins stay marked active in the
# DB, and the SPA breaks with `Unexpected token '<'` (HTML returned for the
# missing JS bundle). The command is idempotent and safe pre-install (it
# checks if the plugins table exists).
php artisan plugin:relink-public 2>/dev/null || true

# Hand off to supervisord (nginx + php-fpm + queue worker + reverb).
exec "$@"
