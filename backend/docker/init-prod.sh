#!/bin/sh
# Préparation au démarrage en production (service « init » de docker-compose.prod.yml) :
# dossiers de stockage, lien des fichiers publics, routes Traefik de la plateforme
# (depuis plateforme.yml.dist, avec le domaine), migrations et profils.
# Les dépendances sont déjà dans l'image (cible prod) : pas de composer install ici.
set -eu
cd /app

: "${DOMAINE:?DOMAINE manquant : copier backend/.env.prod.example en backend/.env.prod et le remplir.}"

case "$DOMAINE" in
    *[!a-z0-9.-]* | .* | *. | *..*)
        echo "DOMAINE invalide : $DOMAINE" >&2
        exit 1
        ;;
esac

mkdir -p storage/app/public storage/app/private/copies storage/app/private/proxy \
    storage/app/private/sauvegardes storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/framework/sante storage/logs bootstrap/cache

if [ "$(readlink public/storage 2>/dev/null || true)" != "../storage/app/public" ]; then
    rm -f public/storage
    ln -s ../storage/app/public public/storage
fi

# Routes Traefik de la plateforme (front, API, Reverb), avec le domaine rempli.
sed "s|__DOMAINE__|${DOMAINE}|g" /modele-plateforme.yml > storage/app/private/proxy/plateforme.yml.tmp
mv storage/app/private/proxy/plateforme.yml.tmp storage/app/private/proxy/plateforme.yml

php artisan migrate --force --no-interaction

# Copie rangée des profils (tri de la recherche de devs par Souk Score), à jour dès le démarrage.
php artisan profils:recalculer --no-interaction
