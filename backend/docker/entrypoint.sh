#!/bin/sh
# Point d'entrée des conteneurs PHP de la plateforme.
#
# 1. Quitte root : le processus prend l'UID et le GID du propriétaire de /app
#    (l'utilisateur de la machine en développement, www-data en production).
# 2. Si le socket Docker est monté (service « moteur » seulement), rejoint son groupe ;
#    de même pour le socket du second démon de développement (/run/dind, GID 2376).
#
# Les secrets du volume « secrets » sont chargés par Laravel lui-même (bootstrap/app.php).
set -eu

if [ "$(id -u)" != "0" ]; then
    exec "$@"
fi

uid=$(stat -c %u /app)
gid=$(stat -c %g /app)

if [ "$uid" = "0" ]; then
    exec "$@"
fi

groupe=$(getent group "$gid" | cut -d: -f1 || true)
if [ -z "$groupe" ]; then
    addgroup -g "$gid" soukdev
    groupe=soukdev
fi

utilisateur=$(getent passwd "$uid" | cut -d: -f1 || true)
if [ -z "$utilisateur" ]; then
    adduser -D -H -h /tmp -u "$uid" -G "$groupe" -s /sbin/nologin soukdev
    utilisateur=soukdev
fi

# En production, le stockage est un volume neuf (root) : il revient au propriétaire du code.
if [ -d /app/storage ]; then
    chown -R "$uid:$gid" /app/storage /app/bootstrap/cache 2>/dev/null || true
fi

if [ -S /var/run/docker.sock ]; then
    gid_docker=$(stat -c %g /var/run/docker.sock)
    groupe_docker=$(getent group "$gid_docker" | cut -d: -f1 || true)
    if [ -z "$groupe_docker" ]; then
        addgroup -g "$gid_docker" dockerhote
        groupe_docker=dockerhote
    fi
    addgroup "$utilisateur" "$groupe_docker" 2>/dev/null || true
fi

# Le second démon (profil « machines ») peut démarrer après le moteur : le groupe est fixe
# (dockerd --group=2376), rejoint dès que le volume de son socket est monté.
if [ -d /run/dind ]; then
    groupe_dind=$(getent group 2376 | cut -d: -f1 || true)
    if [ -z "$groupe_dind" ]; then
        addgroup -g 2376 dind
        groupe_dind=dind
    fi
    addgroup "$utilisateur" "$groupe_dind" 2>/dev/null || true
fi

export HOME=/tmp
exec su-exec "$utilisateur" "$@"
