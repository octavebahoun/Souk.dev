# Cotis’Asso (démo)

Appli cobaye du jeu de démo de Souk.dev : un site statique servi par nginx, pour que le jury
puisse déployer une vraie copie depuis le Store. Les chiffres affichés sont fictifs.

`php artisan demo:miroirs` en fait un dépôt Git local que le moteur clone à la place de
`https://github.com/soukdemo/cotis-asso` (développement, ou mode jury : `DEMO_JURY=true`).
