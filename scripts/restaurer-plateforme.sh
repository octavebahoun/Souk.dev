#!/bin/sh
# Test de restauration : rejoue une sauvegarde dans des volumes jetables et vérifie que
# la plateforme repart (migrations à jour, tables présentes, grand livre cohérent).
# Usage : ./scripts/restaurer-plateforme.sh <dossier> <horodatage>
#   ex. ./scripts/restaurer-plateforme.sh ./sauvegardes 20261029-030000
# Exige Docker (arrêté sur le poste de dev au moment de l'écriture : testé au retour,
# voir PROGRESS.md). Ne touche jamais aux volumes de production (préfixe « test-restauration »).
set -eu

cd "$(dirname "$0")/.."

dossier=${1:?Usage : $0 <dossier> <horodatage>}
date=${2:?Usage : $0 <dossier> <horodatage>}

case "$date" in
    [0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9]) ;;
    *) echo "Horodatage invalide (attendu : AAAAMMJJ-HHMMSS) : $date" >&2; exit 1 ;;
esac

for fichier in "soukdev-$date.sql.gz" "stockage-$date.tar.gz" "routes-$date.tar.gz" "letsencrypt-$date.tar.gz" "sommes-$date.txt"; do
    [ -f "$dossier/$fichier" ] || { echo "Manquant : $dossier/$fichier" >&2; exit 1; }
done

echo "== Sommes de contrôle =="
(cd "$dossier" && sha256sum -c "sommes-$date.txt")

prefixe="test-restauration-$date"
base="soukdev_test_restauration"
nettoyer() {
    echo "== Nettoyage =="
    docker rm -f "$prefixe-db" >/dev/null 2>&1 || true
    docker volume rm "$prefixe-stockage" "$prefixe-routes" "$prefixe-letsencrypt" "$prefixe-db" >/dev/null 2>&1 || true
}
trap nettoyer EXIT

echo "== Volumes jetables =="
for volume in stockage routes letsencrypt db; do
    docker volume rm "$prefixe-$volume" >/dev/null 2>&1 || true
    docker volume create "$prefixe-$volume" >/dev/null
done

echo "== Restauration des volumes =="
for volume in stockage routes letsencrypt; do
    docker run --rm \
        -v "$prefixe-$volume:/donnees" \
        -v "$(pwd)/$dossier:/entree:ro" \
        alpine:3.21 tar xzf "/entree/${volume}-$date.tar.gz" -C /donnees
done

echo "== Base temporaire =="
docker run -d --name "$prefixe-db" \
    -v "$prefixe-db:/var/lib/postgresql/data" \
    -e POSTGRES_DB="$base" -e POSTGRES_USER=soukdev -e POSTGRES_PASSWORD=test-restauration \
    postgres:16-alpine >/dev/null

for attente in $(seq 1 30); do
    docker exec "$prefixe-db" pg_isready -U soukdev -d "$base" >/dev/null 2>&1 && break
    sleep 2
done
docker exec "$prefixe-db" pg_isready -U soukdev -d "$base"

echo "== Restauration de la base =="
gunzip -c "$dossier/soukdev-$date.sql.gz" | docker exec -i "$prefixe-db" psql -U soukdev -d "$base" -v ON_ERROR_STOP=1 -q

echo "== Vérifications =="
requete() {
    docker exec -i "$prefixe-db" psql -U soukdev -d "$base" -t -A -c "$1"
}

migrations=$(requete "SELECT count(*) FROM migrations;")
[ "$migrations" -gt 0 ] || { echo "ÉCHEC : aucune migration enregistrée." >&2; exit 1; }
echo "Migrations enregistrées : $migrations"

tables=$(requete "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';")
[ "$tables" -gt 20 ] || { echo "ÉCHEC : seulement $tables tables." >&2; exit 1; }
echo "Tables : $tables"

ecart=$(requete "SELECT COALESCE(sum(montant), 0) FROM ecritures;")
[ "$ecart" = "0" ] || { echo "ÉCHEC : le grand livre ne solde pas ($ecart)." >&2; exit 1; }
echo "Grand livre : solde à zéro."

echo "RESTAURATION RÉUSSIE : la sauvegarde $date repart."
