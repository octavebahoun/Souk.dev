#!/bin/sh
# Génère une seule fois les secrets internes de la plateforme, dans le volume
# « secrets » (jamais dans le dépôt) : mot de passe PostgreSQL et clés Reverb.
# Les clés des services externes (SasPay, DeepSeek, Resend, GitHub) restent dans backend/.env.
#
# plateforme.env est lisible par l'utilisateur de l'appli (Laravel le charge au démarrage,
# voir bootstrap/app.php) ; db_password reste réservé à root. Le volume n'est monté que
# dans les conteneurs de la plateforme.
set -eu

dossier=/run/soukdev
fichier=$dossier/plateforme.env

if [ -s "$fichier" ] && [ -s "$dossier/db_password" ]; then
    chmod 0644 "$fichier"
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
chmod 0644 "$fichier.tmp"
mv "$fichier.tmp" "$fichier"

echo "Secrets générés dans le volume."
