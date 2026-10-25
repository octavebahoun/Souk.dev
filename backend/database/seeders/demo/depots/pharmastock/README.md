# PharmaStock (démo)

Appli cobaye du jeu de démo de Souk.dev : un site statique servi par nginx, pour essayer de bout
en bout le paiement, le déploiement, la suspension et la réactivation d'une copie, sans dépôt
externe. Les chiffres affichés sont fictifs.

En développement, `php artisan demo:miroirs` en fait un dépôt Git local que le moteur clone à la
place de `https://github.com/soukdemo/pharmastock` (voir `MOTEUR_MIROIRS`).
