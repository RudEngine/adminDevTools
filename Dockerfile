# syntax=docker/dockerfile:1.7
#
# Production image for Dokploy (or any plain `docker run`).
#
#   nginx + php-fpm + supervisor in one container, listening on :8080.
#   Everything that serves a request or runs application code does so as
#   www-data; only the process masters and the first half of the entrypoint
#   keep root, to chown the mounted volumes and own the log pipe.
#
# Dokploy setup
#   Build type .............. Dockerfile  (path: ./Dockerfile)
#   Port ..................... 8080  (Domains → Container Port)
#   Required env ............. APP_KEY, APP_URL, and the DB_* / CACHE_* /
#                              SESSION_* values for the chosen services
#   Optional env ............. RUN_MIGRATIONS, RUN_QUEUE_WORKER, RUN_SCHEDULER
#   Volume (if state is kept)  /var/www/html/storage
#
# Build:  docker build -t admin:latest .
# Run:    docker run --rm -p 8080:8080 --env-file .env.production admin:latest

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

###############################################################################
# base — PHP runtime plus the web/process tooling, shared by every later stage
###############################################################################
FROM php:${PHP_VERSION}-fpm-alpine AS base

ENV APP_DIR=/var/www/html \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_HOME=/tmp/composer

RUN apk add --no-cache \
        nginx \
        supervisor \
        su-exec \
        curl \
        tzdata \
        icu-data-full \
    && rm -rf /var/cache/apk/*

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        redis \
        zip

WORKDIR ${APP_DIR}

###############################################################################
# vendor — composer dependencies, production only
###############################################################################
FROM base AS vendor

RUN install-php-extensions @composer

# Resolve dependencies before the source is copied so a code-only change does
# not invalidate the (slow) install layer.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-progress

COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --no-interaction

###############################################################################
# assets — Vite build (CSS, JS, self-hosted fonts)
###############################################################################
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts --no-audit --no-fund

# The Vite config self-hosts its webfonts, which means `npm run build` pulls
# them over the network — fine on a Dokploy builder, but it is the one build
# step that is not hermetic.

COPY . .
RUN npm run build

###############################################################################
# production — the shipped image
###############################################################################
FROM base AS production

ARG BUILD_VERSION=dev
ARG BUILD_COMMIT=unknown

LABEL org.opencontainers.image.title="admin" \
      org.opencontainers.image.version="${BUILD_VERSION}" \
      org.opencontainers.image.revision="${BUILD_COMMIT}"

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stack \
    LOG_STACK=stderr \
    LOG_LEVEL=warning

# Opt-in runtime behaviour. These must be defined even when off: supervisor
# reads RUN_QUEUE_WORKER / RUN_SCHEDULER through %(ENV_…)s and refuses to start
# if the variable is missing. Override any of them from the Dokploy
# environment.
ENV RUN_MIGRATIONS=false \
    RUN_QUEUE_WORKER=false \
    RUN_SCHEDULER=false

# --- server / process configuration ------------------------------------------
COPY docker/nginx/nginx.conf           /etc/nginx/nginx.conf
COPY docker/php/php.ini                /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/php/opcache.ini            /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh              /usr/local/bin/entrypoint

# Replace the stock pool outright — two files declaring [www] would collide.
COPY docker/php-fpm/www.conf           /usr/local/etc/php-fpm.d/www.conf
COPY docker/php-fpm/zz-global.conf     /usr/local/etc/php-fpm.d/zz-global.conf

# Three things happen here:
#   - zz-docker.conf would re-declare [www] and fight our pool, and the stock
#     www.conf.default plus the default nginx vhost are dead weight;
#   - nginx's workers run as www-data, so its runtime and scratch paths have to
#     belong to www-data rather than the distro's nginx user;
#   - both configs are parsed at build time, so a typo fails the build instead
#     of the deployment.
RUN set -eux; \
    rm -f /usr/local/etc/php-fpm.d/zz-docker.conf \
          /usr/local/etc/php-fpm.d/www.conf.default \
          /etc/nginx/http.d/default.conf; \
    chmod +x /usr/local/bin/entrypoint; \
    mkdir -p /run/nginx /run/php-fpm /var/lib/nginx/tmp; \
    chown -R www-data:www-data /run/nginx /run/php-fpm /var/lib/nginx; \
    nginx -t; \
    php-fpm --test

# --- application --------------------------------------------------------------
COPY --chown=root:root . ${APP_DIR}
COPY --from=vendor --chown=root:root ${APP_DIR}/vendor ${APP_DIR}/vendor
COPY --from=vendor --chown=root:root ${APP_DIR}/bootstrap/cache ${APP_DIR}/bootstrap/cache
COPY --from=assets --chown=root:root /app/public/build ${APP_DIR}/public/build

# The application tree stays root-owned and read-only to www-data; only the
# paths Laravel actually writes to are handed over, so a code-execution bug
# cannot rewrite the application. `public/storage` is baked in as a relative
# symlink, so `artisan storage:link` is never needed at runtime.
RUN set -eux; \
    rm -rf ${APP_DIR}/docker ${APP_DIR}/.env ${APP_DIR}/.env.*; \
    ln -sfn ../storage/app/public ${APP_DIR}/public/storage; \
    mkdir -p ${APP_DIR}/storage/app/private \
             ${APP_DIR}/storage/app/public \
             ${APP_DIR}/storage/framework/cache/data \
             ${APP_DIR}/storage/framework/sessions \
             ${APP_DIR}/storage/framework/views \
             ${APP_DIR}/storage/logs; \
    chown -R www-data:www-data ${APP_DIR}/storage ${APP_DIR}/bootstrap/cache; \
    chmod -R u=rwX,g=rX,o= ${APP_DIR}/storage ${APP_DIR}/bootstrap/cache; \
    php --version; \
    php -r 'exit((int) ! (extension_loaded("Zend OPcache") && extension_loaded("redis") && extension_loaded("pdo_pgsql") && extension_loaded("intl")));'

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1:8080/up || exit 1

# Runs as root: the entrypoint has to chown the mounted volumes, and the
# nginx / php-fpm / supervisord masters need the container's root-owned log
# pipe. Everything that handles a request or runs application code drops to
# www-data — see the privilege-model note in docker/entrypoint.sh.
ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
