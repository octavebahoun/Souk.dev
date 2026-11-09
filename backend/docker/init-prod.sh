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

# Routes Traefik de la plateforme (front, API, Reverb), avec le domaine et les ports remplis.
# Derrière un proxy qui termine déjà le TLS (MOTEUR_TLS_TRAEFIK=false) : pas de bloc « tls »
# (deux lignes : « tls: » puis son certResolver), Traefik sert le HTTP que le proxy lui passe.
routes=storage/app/private/proxy/plateforme.yml
sed -e "s|__DOMAINE__|${DOMAINE}|g" \
    -e "s|__PORT_API__|${PORT_API:-8000}|g" \
    -e "s|__PORT_REVERB__|${PORT_REVERB:-8080}|g" \
    -e "s|__PORT_FRONT__|${PORT_FRONT:-8081}|g" \
    /modele-plateforme.yml > "$routes.tmp"
if [ "${MOTEUR_TLS_TRAEFIK:-true}" = "false" ]; then
    sed -i '/^      tls:$/{N;d;}' "$routes.tmp"
fi
mv "$routes.tmp" "$routes"

php artisan migrate --force --no-interaction

# Copie rangée des profils (tri de la recherche de devs par Souk Score), à jour dès le démarrage.
php artisan profils:recalculer --no-interaction
