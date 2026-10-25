# Reverse proxy des copies

Traefik sert chaque copie en ligne sur son sous-domaine (`MOTEUR_URL_COPIE`, par défaut `http://{copie}.localhost`) et n'expose que son `service_web`.

- Quand une copie passe `en_ligne`, le moteur écrit `{copie}.yml` dans `MOTEUR_PROXY_ROUTES`, vers l'adresse et le port du service web qu'il vient de sonder.
- À l'arrêt, le fichier est retiré avant `docker compose down`.
- Traefik ne lit pas les labels Docker : un dépôt ne peut ni exposer un autre service, ni prendre le sous-domaine d'une autre copie.

## Lancer

```sh
docker compose -f infra/proxy/docker-compose.yml up -d
```

En local, `*.localhost` pointe déjà vers la machine. Sur le VPS, il faut un DNS joker (`*.soukdev.com`) et `MOTEUR_URL_COPIE=https://{copie}.soukdev.com` (HTTPS : certificat joker à ajouter dans `traefik.yml`).
