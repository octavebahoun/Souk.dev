#!/bin/sh
# Sauvegarde de la plateforme de production : base PostgreSQL (pg_dump) + volumes
# (stockage des copies et de leurs sauvegardes, routes Traefik, certificats).
# Usage : ./scripts/sauvegarde-plateforme.sh [dossier]  (défaut : ./sauvegardes)
# À lancer chaque nuit depuis le VPS (cron de l'opérateur, voir docs/exploitation.md).
set -eu

cd "$(dirname "$0")/.."

dossier=${1:-./sauvegardes}
date=$(date +%Y%m%d-%H%M%S)
mkdir -p "$dossier"

compose="docker compose -f docker-compose.prod.yml"
# Projet Compose : « soukdev » (name: de docker-compose.prod.yml), ou COMPOSE_PROJECT_NAME pour un
# essai à côté d'une autre plateforme. Ses volumes s'appellent <projet>_<volume>.
projet=${COMPOSE_PROJECT_NAME:-soukdev}

echo "== Base de données =="
# Sans pipe : en sh, le code d'un pipe est celui de gzip, un pg_dump raté passerait
# pour réussi. Le dump part dans un temporaire, contrôlé avant compression.
tmp=$(mktemp)
$compose exec -T db pg_dump -U soukdev --no-owner --no-acl soukdev > "$tmp"
[ -s "$tmp" ] || { echo "ÉCHEC : le dump de la base est vide (pg_dump a échoué)." >&2; rm -f "$tmp"; exit 1; }
gzip -c "$tmp" > "$dossier/soukdev-$date.sql.gz"
rm -f "$tmp"

echo "== Volumes =="
for volume in stockage routes letsencrypt; do
    docker run --rm \
        -v "${projet}_${volume}:/donnees:ro" \
        -v "$(pwd)/$dossier:/sortie" \
        alpine:3.21 tar czf "/sortie/${volume}-$date.tar.gz" -C /donnees .
done

echo "== Sommes de contrôle =="
(cd "$dossier" && sha256sum "soukdev-$date.sql.gz" "stockage-$date.tar.gz" "routes-$date.tar.gz" "letsencrypt-$date.tar.gz" > "sommes-$date.txt")

ls -lh "$dossier"/*"$date"*
echo "Sauvegarde terminée dans $dossier."
