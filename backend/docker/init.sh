#!/bin/sh
# Préparation au démarrage (service « init ») : dépendances, clé de l'appli,
# dossiers de stockage, lien des fichiers publics et migrations.
set -eu
cd /app

if [ ! -f .env ]; then
    cp .env.example .env
fi

composer install --no-interaction --prefer-dist --no-progress

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

mkdir -p storage/app/public storage/app/private/copies storage/app/private/proxy \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/framework/sante storage/logs bootstrap/cache

# Lien relatif : il marche dans le conteneur (/app) comme sur la machine.
if [ "$(readlink public/storage 2>/dev/null || true)" != "../storage/app/public" ]; then
    rm -f public/storage
    ln -s ../storage/app/public public/storage
fi

php artisan migrate --force --no-interaction
