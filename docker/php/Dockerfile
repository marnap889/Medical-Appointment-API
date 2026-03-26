FROM php:8.5-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        libxml2-dev \
        libonig-dev \
    && docker-php-ext-install intl pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini

FROM php-base AS php-prod-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --prefer-dist \
    --no-progress \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-scripts

FROM php-base AS php-prod

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY docker/php/conf.d/opcache-jit.ini /usr/local/etc/php/conf.d/zz-opcache-jit.ini

COPY . /app
COPY --from=php-prod-deps /app/vendor /app/vendor

RUN mkdir -p /app/var/cache /app/var/log \
    && chown -R www-data:www-data /app/var

USER www-data

CMD ["php-fpm"]

FROM php-base AS php-ci-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* symfony.lock* ./

RUN composer install \
    --no-interaction \
    --no-plugins \
    --prefer-dist \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

FROM php-base AS php-ci

ENV APP_ENV=test \
    APP_DEBUG=0

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY . /app
COPY --from=php-ci-deps /app/vendor /opt/vendor
COPY --from=php-ci-deps /app/vendor /app/vendor

RUN mkdir -p /app/var/cache /app/var/log \
    && chown -R www-data:www-data /app/var

USER www-data

CMD ["php-fpm"]

FROM php-base AS php-dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

CMD ["php-fpm"]
