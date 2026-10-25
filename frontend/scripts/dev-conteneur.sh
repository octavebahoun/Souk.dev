#!/bin/sh
# Serveur Vite du conteneur « front » (docker-compose.yml à la racine).
set -eu

# Seule la clé publique de Reverb (envoyée aux navigateurs de toute façon) arrive ici.
if [ -r /run/soukdev-public/public.env ]; then
    set -a
    . /run/soukdev-public/public.env
    set +a
fi

# node_modules vit dans un volume (binaires pour Alpine) : réinstallé quand le lockfile change.
if ! cmp -s package-lock.json node_modules/.soukdev-lock 2>/dev/null; then
    npm ci --no-audit --no-fund
    cp package-lock.json node_modules/.soukdev-lock
fi

exec npm run dev -- --host 0.0.0.0 --port 5173 --strictPort
