#!/bin/sh
# Docker Compose de production : toujours avec backend/.env.prod, que Compose ne lit pas de
# lui-même pour remplacer ${DOMAINE}, ${ACME_EMAIL}… dans docker-compose.prod.yml.
# Usage : ./scripts/prod.sh <commande compose…>   ex. ./scripts/prod.sh up -d --build
set -eu

cd "$(dirname "$0")/.."

[ -f backend/.env.prod ] || { echo "backend/.env.prod manquant : copier backend/.env.prod.example et le remplir (docs/exploitation.md)." >&2; exit 1; }

exec docker compose --env-file backend/.env.prod -f docker-compose.prod.yml "$@"
