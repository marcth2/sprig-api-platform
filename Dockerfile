#################################################################
# Sprig API Platform Dockerfile
# Multi-stage: base → dev / ci
# Web server: Nginx + PHP-FPM (separate containers via docker-compose)
#################################################################

FROM php:8.5-fpm-alpine AS base

# System dependencies + PECL build tools
RUN apk add --no-cache \
    libzip-dev \
    icu-dev \
    icu-libs \
    oniguruma-dev \
    zip \
    unzip \
    curl \
    $PHPIZE_DEPS

# Core PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    bcmath \
    zip \
    mbstring

# phpredis — Laravel 13 recommended over predis
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/php.ini

WORKDIR /var/www/html

#################################################################
# DEV IMAGE — Xdebug enabled, OPcache intentionally off
#################################################################

FROM base AS dev

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache su-exec linux-headers $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS

# Create a non-root user matching the host UID/GID so bind-mounted files
# are owned by the developer rather than root.
RUN addgroup -g ${GID} -S app \
    && adduser -u ${UID} -S -G app app

COPY docker/php/phpstan.ini /usr/local/etc/php/conf.d/phpstan.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
# Override FPM pool user — must come after www.conf alphabetically (zzz- prefix)
COPY docker/php/fpm-dev.conf /usr/local/etc/php-fpm.d/zzz-dev.conf

ENTRYPOINT ["docker/php/dev-entrypoint.sh"]

#################################################################
# CI IMAGE — PCOV for coverage, OPcache intentionally off
#################################################################

FROM base AS ci

ENV APP_ENV=testing

COPY docker/php/ci.ini /usr/local/etc/php/conf.d/zzz-ci.ini

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS
