#!/bin/sh
# Point d'entrée des conteneurs PHP de la plateforme.
#
# 1. Quitte root : le processus prend l'UID et le GID du propriétaire de /app
#    (l'utilisateur de la machine en développement, www-data en production).
# 2. Si le socket Docker est monté (service « moteur » seulement), rejoint son groupe.
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

if [ -S /var/run/docker.sock ]; then
    gid_docker=$(stat -c %g /var/run/docker.sock)
    groupe_docker=$(getent group "$gid_docker" | cut -d: -f1 || true)
    if [ -z "$groupe_docker" ]; then
        addgroup -g "$gid_docker" dockerhote
        groupe_docker=dockerhote
    fi
    addgroup "$utilisateur" "$groupe_docker" 2>/dev/null || true
fi

export HOME=/tmp
exec su-exec "$utilisateur" "$@"
