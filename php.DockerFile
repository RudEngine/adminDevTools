FROM php:8.4-fpm

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    supervisor

#COPY ./supervisor/supervisord.conf /etc/supervisor

#CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]

WORKDIR /var/www

RUN apt-get update \
    && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev libpq-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo pdo_pgsql \
    && if ! php -m | grep -q 'redis'; then \
        pecl install redis && docker-php-ext-enable redis; \
    fi
