#!/bin/sh
set -e

APP_ROOT=/var/www/html
cd "$APP_ROOT"

# Generate APP_KEY on first boot if the operator didn't set one.
if [ -z "${APP_KEY:-}" ] && [ ! -f storage/.app_key ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
    echo "$APP_KEY" > storage/.app_key
elif [ -z "${APP_KEY:-}" ] && [ -f storage/.app_key ]; then
    APP_KEY="$(cat storage/.app_key)"
    export APP_KEY
fi

# Persistent dirs that must exist on a fresh named volume.
mkdir -p storage/app/public/branding \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/database \
         bootstrap/cache

# SQLite default path — create the file if it doesn't exist yet.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f storage/database/database.sqlite ]; then
    touch storage/database/database.sqlite
fi

# Ensure writable perms (volumes from Portainer start as root-owned).
chown -R www-data:www-data storage bootstrap/cache plugins 2>/dev/null || true

# Run migrations if the panel is marked installed OR we're on a fresh SQLite file.
# The Setup Wizard will run its own migrations on first install regardless.
if [ "${PANEL_INSTALLED:-false}" = "true" ]; then
    php artisan migrate --force || true
    php artisan config:cache || true
    php artisan route:cache || true
fi

# storage:link is idempotent; ignore if target exists.
php artisan storage:link 2>/dev/null || true

# Hand off to supervisord (nginx + php-fpm + queue worker).
exec "$@"
