# syntax=docker/dockerfile:1
#
# Image unique de la plateforme Souk.dev : API (FrankenPHP), workers, Reverb et
# planificateur. Seul le service « moteur » reçoit le socket Docker : l'API ne
# lance jamais de conteneur et n'exécute jamais le code d'un dépôt.

ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1.13.3-php8.4-alpine

FROM ${FRANKENPHP_IMAGE} AS base

# Extensions PHP de l'appli (PostgreSQL, GD pour les captures, pcntl pour les workers),
# puis git et la CLI Docker pour le moteur, su-exec pour quitter root au démarrage.
RUN install-php-extensions pdo_pgsql gd intl zip bcmath pcntl sockets opcache exif \
    && apk add --no-cache git openssh-client docker-cli docker-cli-compose su-exec curl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-soukdev.ini
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/soukdev-entrypoint

ENV COMPOSER_HOME=/tmp/composer \
    XDG_CONFIG_HOME=/tmp/caddy/config \
    XDG_DATA_HOME=/tmp/caddy/data

WORKDIR /app

ENTRYPOINT ["soukdev-entrypoint"]
CMD ["frankenphp", "php-server", "--listen", ":8000", "--root", "public/"]

# Développement : le code est monté depuis ./backend, le service init installe les dépendances.
FROM base AS dev

# Production : code et dépendances dans l'image, sans outils de développement.
FROM base AS prod

ENV APP_ENV=production \
    APP_DEBUG=false

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && rm -rf tests .claude .agents .env \
    && chown -R www-data:www-data storage bootstrap/cache
