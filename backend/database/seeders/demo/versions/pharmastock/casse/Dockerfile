FROM nginx:1.31.8-alpine
COPY site/ /usr/share/nginx/html/
# Version volontairement cassée du jeu de démo : le serveur s'arrête au démarrage,
# la mise à jour doit revenir d'elle-même à la version précédente.
CMD ["sh", "-c", "echo 'Configuration manquante : SERVICE_PAIEMENT' >&2; exit 1"]
