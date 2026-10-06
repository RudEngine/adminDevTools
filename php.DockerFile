FROM php:8.4-fpm

# pecl.php.net недоступен из локальной сети, поэтому PHP-расширения
# собираются из исходников с GitHub.
ARG PHPREDIS_VERSION=6.3.0
ARG XDEBUG_VERSION=3.5.3

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    supervisor

#COPY ./supervisor/supervisord.conf /etc/supervisor

#CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]

WORKDIR /var/www

RUN apt-get update \
    && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev libpq-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo pdo_pgsql

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends $PHPIZE_DEPS; \
    mkdir -p /tmp/ext/redis /tmp/ext/xdebug; \
    curl -fsSL "https://github.com/phpredis/phpredis/archive/refs/tags/${PHPREDIS_VERSION}.tar.gz" \
        | tar xz -C /tmp/ext/redis --strip-components=1; \
    curl -fsSL "https://github.com/xdebug/xdebug/archive/refs/tags/${XDEBUG_VERSION}.tar.gz" \
        | tar xz -C /tmp/ext/xdebug --strip-components=1; \
    for ext in redis xdebug; do \
        cd "/tmp/ext/$ext"; \
        phpize; \
        ./configure; \
        make -j"$(nproc)"; \
        make install; \
    done; \
    docker-php-ext-enable redis; \
    rm -rf /tmp/ext /var/lib/apt/lists/*

# Xdebug намеренно НЕ включается через docker-php-ext-enable: он активируется
# только там, где смонтирован dockerConfigs/php/xdebug.ini (локальный compose).