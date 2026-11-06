# Bookworm (glibc 2.36) läuft auf dem QNAP-Kernel 5.10.60. Nicht "php:8.4-fpm" ohne
# Suffix verwenden: das ist Trixie (glibc 2.41, nutzt fchmodat2 → "Bad address").
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libsqlite3-dev \
        libgmp-dev \
        libxml2-dev \
        sqlite3 \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install \
        pdo_sqlite \
        pdo_mysql \
        pcntl \
        bcmath \
        gmp \
        intl \
        zip \
        opcache

# phpredis für Cache, Sessions und Queue (REDIS_CLIENT=phpredis).
RUN printf '\n\n\n\n\n' | pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

# PHP-FPM als Host-User, damit Writes in den Bind-Mount (storage/, SQLite-DB)
# mit den Datei-Eigentümern übereinstimmen.
ARG WWWUSER=1000
ARG WWWGROUP=100
RUN usermod -u ${WWWUSER} www-data \
    && usermod -g ${WWWGROUP} www-data

WORKDIR /var/www/html
