#!/bin/sh
# Serveur Vite du conteneur « front » (docker-compose.yml à la racine).
set -eu

if [ -r /run/soukdev/plateforme.env ]; then
    set -a
    . /run/soukdev/plateforme.env
    set +a
    export VITE_REVERB_KEY="$REVERB_APP_KEY"
fi

# node_modules vit dans un volume (binaires pour Alpine) : réinstallé quand le lockfile change.
if ! cmp -s package-lock.json node_modules/.soukdev-lock 2>/dev/null; then
    npm ci --no-audit --no-fund
    cp package-lock.json node_modules/.soukdev-lock
fi

exec npm run dev -- --host 0.0.0.0 --port 5173 --strictPort
