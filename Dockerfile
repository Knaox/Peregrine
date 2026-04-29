# syntax=docker/dockerfile:1.6
#
# Peregrine — production image (~300 MB per arch on Alpine)
# Two stages: (1) pnpm frontend build, (2) PHP-FPM + nginx + supervisord runtime.
# Published to ghcr.io/knaox/peregrine by .github/workflows/docker.yml.
# Self-serves HTTP on :8080 — no external nginx required.

# ---------------------------------------------------------------
# Stage 1 — frontend (node, throw-away)
# ---------------------------------------------------------------
FROM node:22-alpine AS frontend-build

RUN corepack enable && corepack prepare pnpm@latest --activate

WORKDIR /build

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile --prefer-offline

COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.ts tsconfig.json postcss.config.js ./
RUN pnpm run build


# ---------------------------------------------------------------
# Stage 2 — runtime (Alpine php-fpm + nginx + supervisord)
# ---------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app

# Runtime libs + build deps for PHP extensions; build deps removed at the end.
RUN apk add --no-cache \
        nginx supervisor curl ca-certificates \
        icu-libs freetype libpng libjpeg-turbo libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev freetype-dev libpng-dev \
        libjpeg-turbo-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath gd intl zip pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# Bring composer in from its own image (no apk pkg for PHP 8.3 Alpine).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Safe Laravel defaults during package:discover — no DB, no cache, no session
# available at build time. SettingsService::get() catches DB exceptions too.
ENV APP_ENV=production \
    APP_DEBUG=false \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    DB_CONNECTION=sqlite \
    DB_DATABASE=:memory: \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

# Phase A — composer deps (cached unless composer.{json,lock} change)
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-autoloader --no-scripts --no-progress

# Phase B — app code, regenerate optimized autoloader + run package:discover
COPY . .
COPY --from=frontend-build /build/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php -d memory_limit=-1 artisan package:discover --ansi \
    && rm -rf tests .github docs marketplace \
              docker-compose.dev.yml docker-compose.full.yml \
              phpunit.xml phpunit.xml.dist \
              storage/logs/*.log \
              /tmp/composer-cache /root/.composer

# Nginx + php-fpm + supervisord + entrypoint
RUN rm -f /etc/nginx/http.d/default.conf /usr/local/etc/php-fpm.d/www.conf.default 2>/dev/null || true
COPY docker/nginx/peregrine.conf /etc/nginx/http.d/peregrine.conf
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/peregrine-entrypoint

RUN chmod +x /usr/local/bin/peregrine-entrypoint \
    && mkdir -p /run/nginx /var/www/html/public/plugins \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/plugins \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/plugins

# Runtime defaults (overridable via docker run -e / compose).
# LOG_CHANNEL=stderr + LOG_STDERR_FORMATTER pipe every Laravel log entry
# to the container's stderr → visible in `docker logs` / Portainer.
ENV DOCKER=true \
    PANEL_INSTALLED=false \
    LOG_CHANNEL=stderr \
    LOG_STDERR_FORMATTER=Monolog\\Formatter\\JsonFormatter

EXPOSE 8080
# Reverb is bound to 127.0.0.1:6001 inside the container and surfaced
# through nginx's /app/ proxy on 8080. We don't EXPOSE 6001 publicly —
# only the operator is allowed to reach Reverb directly (e.g. for debug).

HEALTHCHECK --interval=30s --timeout=5s --start-period=45s --retries=5 \
    CMD curl -fsS http://localhost:8080/up || exit 1

ENTRYPOINT ["peregrine-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
