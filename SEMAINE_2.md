# Semaine 2 — du 13 au 19 octobre

Priorité : le fil de la démo doit marcher de bout en bout (bug posté, correctif accepté, appli publiée, déployée, mission demandée). Une PR = une tâche, petite.

## Oktav — missions, puis les ajouts hors MVP

Branche `oktav/missions`, en dehors du MVP : je ne touche pas aux branches des autres.

Fait : lecture des missions, fil privé, note, calcul de la réputation et du Souk Score.

- Créer une mission : `POST /devs/{username}/missions` dès que la branche de Wasfade est sur `main` (Sanctum et `username`), puis `POST /apps/{id}/missions` quand le Store de Mourchid existe.
- Brancher la réputation et le Souk Score sur `GET /me` et `GET /devs/{username}` (aujourd'hui des zéros dans `ProfilCalcule`).
- Ensuite, dans l'ordre : recherche de devs (`GET /devs`), tableau de bord, Souk AI.

**Terminé quand** : un client demande une mission (sur une appli ou directement à un dev), échange en privé avec l'auteur, puis la termine en le notant ; un autre compte reçoit `403`.

## Wasfade — correctifs, profil public, événements

Fait (branche `wasfade`) : Sanctum, connexion GitHub, lien magique, discussions, messages, canaux, Reverb.

- D'abord : ouvrir la PR de `wasfade` vers `main`. Les Missions en dépendent.
- Correctifs : `POST` et `GET /discussions/{id}/correctifs`, `POST /correctifs/{id}/accepter`. Auteur de la discussion seulement, un seul correctif accepté, la discussion passe à `resolue`. Événements Reverb `correctif.propose` et `correctif.accepte`.
- Profil public : `GET /devs/{username}`.
- `GET /technos`.
- Événements : `GET` et `POST /events`, `GET`, `PATCH` et `DELETE /events/{id}`, inscription, participants et avis (règles dans `DECISIONS.md`, section Événements, et canaux dans `REVERB.md`).

**Terminé quand** : un dev propose un correctif, l'auteur de la discussion l'accepte et les autres restent visibles ; un dev crée un événement, d'autres s'y inscrivent puis laissent un avis après la date.

## Mourchid — Publier et Store

Fait : moteur branché sur l'API (file d'attente, états, Reverb), arrêt d'une copie, limites par `taille`, mesure de la mémoire, backend intégré.

- Juste avant `up`, vérifier la configuration finale avec `docker compose config --format json` (les trois fichiers réunis) et refuser ce que `VerificateurCompose` refuse. C'est un filet en plus de la lecture du YAML.
- Store : `GET /apps` (filtres et pagination), `GET /apps/{id}`, `GET /categories`.
- Publier : `POST /apps/verification`, `PATCH` et `DELETE /apps/{id}`, `POST /apps/{id}/captures`.
- Copie de test : `POST /discussions/{id}/copie-test` (3 par heure), état diffusé par `copie_test.etat`.
- Après les correctifs de Wasfade : `POST /correctifs/{id}/deploiement`.

**Terminé quand** : une appli publiée apparaît dans le Store avec sa fiche, un client la déploie en un clic, et l'auteur d'une discussion lance une copie de test.

## Hope — les écrans du fil de la démo

Rien n'est encore sur `main` côté front. On suit le fil de la démo, dans l'ordre, sur la fausse API (`npm run prism`).

- Connexion : GitHub pour un dev, lien magique pour un client.
- Échange : fil des discussions, création d'une discussion, page d'une discussion (démo dans son cadre isolé, messages, correctifs, accepter).
- Publier une appli (avec le choix de la `taille`), puis le Store et la fiche d'une appli.
- Déployer en un clic avec le suivi des états, puis « Mes copies ».
- Missions : demander, fil privé, noter.
- Ensuite : profil, recherche de devs, tableau de bord, événements, d'après le document du designer.

**Terminé quand** : on rejoue le fil de la démo de bout en bout sur la fausse API.

## Jean-Baptiste — sécurité avant publication

Reporté de la semaine 1 :

- CI : bloquer une PR qui s'écarte du contrat (`openapi.yaml`).
- Reverse proxy : un sous-domaine par copie, seul `service_web` exposé.

Semaine 2 :

- Secrets : lancer gitleaks (MIT) sur le dépôt cloné, à la vérification et à la publication. Un secret trouvé : réponse `422`, publication refusée.
- `docker-compose.yml` dangereux : réutiliser la vérification écrite par Mourchid dans le moteur (semaine 1), et la brancher aussi sur `POST /apps/verification` et `POST /apps`.
- Dépendances : lancer Trivy (Apache 2.0) après la publication, en tâche de fond, et remplir le champ `securite` de l'appli (`en_cours`, puis `verifie` ou `problemes`).
- `POST /apps/{id}/securite` : relance de l'analyse par l'auteur.
- Avec Hope : le badge vert « Security Checked » ou orange « Problèmes détectés » sur la carte et la fiche d'une appli, et le rapport sur la fiche.

**Terminé quand** : une PR qui s'écarte du contrat est bloquée ; l'appli cobaye répond sur son sous-domaine ; un dépôt contenant un faux secret est refusé, et une appli avec une dépendance vulnérable connue reçoit le badge orange avec son rapport.
