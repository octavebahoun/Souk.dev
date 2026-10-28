# Carnet de commandes (appli de démo)

Appli cobaye du jeu de démo de Souk.dev, sans donnée réelle. Elle montre le backend intégré :
connexion des utilisateurs par code SMS (adresse donnée par `SOUKDEV_AUTH_URL`), puis lecture et
écriture de leurs notes par l'API PostgREST de la copie, protégées ligne à ligne
(`soukdev.utilisateur_id()` dans les politiques de `db/001_notes.sql`).
