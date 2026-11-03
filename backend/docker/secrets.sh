#!/bin/sh
# Génère une seule fois les secrets internes de la plateforme, dans le volume
# « secrets » (jamais dans le dépôt) : mot de passe PostgreSQL et clés Reverb.
# Les clés des services externes (SasPay, DeepSeek, Resend, GitHub) restent dans backend/.env.
#
# - plateforme.env : lisible par root et par le groupe du code (/app), chargé par Laravel ;
# - db_password : root seulement (lu par l'entrée de PostgreSQL) ;
# - public.env (volume « secrets_publics ») : la seule clé publique de Reverb, pour le front.
set -eu

dossier=/run/soukdev
public=/run/soukdev-public
fichier=$dossier/plateforme.env
groupe=$(stat -c %g /app)

proteger() {
    chown "root:$groupe" "$fichier"
    chmod 0640 "$fichier"
    chmod 0600 "$dossier/db_password"
    mkdir -p "$public"
    grep '^REVERB_APP_KEY=' "$fichier" | sed 's/^REVERB_APP_KEY=/VITE_REVERB_KEY=/' > "$public/public.env.tmp"
    chmod 0644 "$public/public.env.tmp"
    mv "$public/public.env.tmp" "$public/public.env"
}

if [ -s "$fichier" ] && [ -s "$dossier/db_password" ]; then
    proteger
    echo "Secrets déjà présents."
    exit 0
fi

aleatoire() {
    php -r 'echo bin2hex(random_bytes((int) $argv[1]));' "$1"
}

umask 077
mot_de_passe=$(aleatoire 24)

{
    echo "DB_PASSWORD=$mot_de_passe"
    echo "REVERB_APP_ID=soukdev"
    echo "REVERB_APP_KEY=$(aleatoire 16)"
    echo "REVERB_APP_SECRET=$(aleatoire 32)"
} > "$fichier.tmp"

printf '%s' "$mot_de_passe" > "$dossier/db_password"
mv "$fichier.tmp" "$fichier"
proteger

echo "Secrets générés dans le volume."
