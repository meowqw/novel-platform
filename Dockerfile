# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-fpm-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html

RUN groupadd --gid 1000 app \
    && useradd --uid 1000 --gid app --shell /bin/bash --create-home app \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R app:app /var/www/html

FROM base AS vendor

COPY --chown=app:app composer.json composer.lock ./
USER app
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM base AS vendor-dev

COPY --chown=app:app composer.json composer.lock ./
USER app
RUN composer install \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /var/www/html/vendor ./vendor

USER app
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi

USER root
RUN chown -R app:app storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

# php-fpm master needs root to drop workers to `app` (see www.conf).
EXPOSE 9000
CMD ["php-fpm", "-F"]

FROM base AS development

ENV APP_ENV=local \
    APP_DEBUG=true

COPY --from=vendor-dev --chown=app:app /var/www/html/vendor ./vendor
COPY --chown=app:app . .

USER root
COPY docker/php/php.dev.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/entrypoint.dev.sh /usr/local/bin/entrypoint.dev.sh
RUN chmod +x /usr/local/bin/entrypoint.dev.sh \
    && chown -R app:app /var/www/html \
    && chmod -R ug+rwx storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/entrypoint.dev.sh"]
EXPOSE 9000
CMD ["php-fpm", "-F"]
