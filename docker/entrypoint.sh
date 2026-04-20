#!/bin/sh
set -e

APP_ROOT=/var/www/html
cd "$APP_ROOT"

# Persistent dirs that must exist on a fresh named volume.
mkdir -p storage/app/public/branding \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/database \
         bootstrap/cache

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

# SQLite default path — create the file if it doesn't exist yet.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f storage/database/database.sqlite ]; then
    touch storage/database/database.sqlite
fi

# Ensure writable perms (volumes from Portainer start as root-owned).
# The Setup Wizard writes .env from the www-data-owned PHP worker, so
# both the file and its parent directory need to be www-data-writable.
chown -R www-data:www-data storage bootstrap/cache plugins 2>/dev/null || true
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
    php artisan config:cache || true
    php artisan route:cache || true
fi

# storage:link is idempotent; ignore if target exists.
php artisan storage:link 2>/dev/null || true

# Hand off to supervisord (nginx + php-fpm + queue worker).
exec "$@"
