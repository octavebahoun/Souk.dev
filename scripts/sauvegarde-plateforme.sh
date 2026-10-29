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

echo "== Base de données =="
$compose exec -T db pg_dump -U soukdev --no-owner --no-acl soukdev | gzip > "$dossier/soukdev-$date.sql.gz"

echo "== Volumes =="
for volume in stockage routes letsencrypt; do
    docker run --rm \
        -v "soukdev_${volume}:/donnees:ro" \
        -v "$(pwd)/$dossier:/sortie" \
        alpine:3.21 tar czf "/sortie/${volume}-$date.tar.gz" -C /donnees .
done

echo "== Sommes de contrôle =="
(cd "$dossier" && sha256sum "soukdev-$date.sql.gz" "stockage-$date.tar.gz" "routes-$date.tar.gz" "letsencrypt-$date.tar.gz" > "sommes-$date.txt")

ls -lh "$dossier"/*"$date"*
echo "Sauvegarde terminée dans $dossier."
