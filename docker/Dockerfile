# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.4

# ---------------------------------------------------------------------------
# base: shared system packages + PHP extensions for every other stage
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS base

RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        icu-dev \
        icu-data-full \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# dev: adds Xdebug + keeps composer available; code is bind-mounted at runtime
# ---------------------------------------------------------------------------
FROM base AS dev

RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS linux-headers

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# build: installs PHP deps and bakes the application code into the image
# ---------------------------------------------------------------------------
FROM base AS build

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --optimize-autoloader \
        --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# ---------------------------------------------------------------------------
# prod: the runtime image actually shipped/deployed
# ---------------------------------------------------------------------------
FROM base AS prod

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# nginx-prod: static/asset server + reverse proxy, ships the same code tree
# so document root paths line up exactly with the prod PHP image
# ---------------------------------------------------------------------------
FROM nginx:alpine AS nginx-prod

COPY --from=build /var/www/html /var/www/html
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
