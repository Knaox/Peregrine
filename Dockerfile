# syntax=docker/dockerfile:1.6
#
# Peregrine — production image
# Multi-stage: (1) pnpm frontend build, (2) PHP-FPM + nginx runtime.
# Published to ghcr.io/knaox/peregrine by .github/workflows/docker.yml.
# The final image self-serves HTTP on :8080 — no external nginx needed.

# ---------------------------------------------------------------
# Stage 1 — frontend
# ---------------------------------------------------------------
FROM node:22-alpine AS frontend-build

RUN corepack enable && corepack prepare pnpm@latest --activate

WORKDIR /build

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.ts tsconfig.json postcss.config.js ./
RUN pnpm run build


# ---------------------------------------------------------------
# Stage 2 — PHP-FPM + nginx runtime
# ---------------------------------------------------------------
FROM php:8.3-fpm AS app

# System deps, nginx, supervisord, PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libzip-dev \
        unzip git curl ca-certificates nginx supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath gd intl zip pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Two-phase composer install for layer caching + safe Laravel boot during
# package:discover (no DB / cache / session available during build).
ENV APP_ENV=production \
    APP_DEBUG=false \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    DB_CONNECTION=sqlite \
    DB_DATABASE=:memory: \
    COMPOSER_ALLOW_SUPERUSER=1

# Phase A — vendor deps only (cached unless composer.json/lock change)
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-autoloader --no-scripts --no-progress

# Phase B — app code, regenerate optimized autoloader + package:discover
COPY . .
COPY --from=frontend-build /build/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

# Nginx + supervisor configs shipped with the app
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf
COPY docker/nginx/peregrine.conf /etc/nginx/conf.d/peregrine.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/peregrine.conf
COPY docker/entrypoint.sh /usr/local/bin/peregrine-entrypoint
RUN chmod +x /usr/local/bin/peregrine-entrypoint

# Laravel writable dirs
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Runtime defaults (overridable via docker run -e / compose)
ENV DOCKER=true \
    PANEL_INSTALLED=false

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=45s --retries=5 \
    CMD curl -fsS http://localhost:8080/up || exit 1

ENTRYPOINT ["peregrine-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
