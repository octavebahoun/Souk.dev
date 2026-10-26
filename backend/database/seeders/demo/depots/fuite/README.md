# Caisse (démo, publication refusée)

Appli cobaye du jeu de démo : `php artisan demo:miroirs` ajoute au miroir local un fichier
`config/paiement.js` contenant une fausse clé de paiement, fabriquée à la volée (elle n'existe
jamais dans le dépôt de Souk.dev). La sécurité avant publication doit la trouver et refuser
la publication.
