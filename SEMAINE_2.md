# Semaine 2 — du 13 au 19 octobre

En préparation : les tâches de chacun seront ajoutées après le bilan de la semaine 1.

## Oktav — missions, puis les ajouts hors MVP

Branche `oktav/missions`, en dehors du MVP : je ne touche pas aux branches des autres.

- Migration et modèle `Mission` : client, auteur, `app_id` (vide pour un recrutement direct), message, budget, délai, statut, note.
- `POST /apps/{id}/missions` (personnaliser une appli) et `POST /devs/{username}/missions` (recruter un dev).
- `GET /missions` et `GET /missions/{id}`, réservés au client et à l'auteur.
- Fil privé : `GET` et `POST /missions/{id}/messages`.
- `POST /missions/{id}/note` : le client note l'auteur (1 à 5) une seule fois, la mission passe à `terminee`.
- Ensuite, dans l'ordre : réputation, Souk Score, tableau de bord, recherche de devs, Souk AI.

**Terminé quand** : un client demande une mission (sur une appli ou directement à un dev), échange en privé avec l'auteur, puis la termine en le notant ; un autre compte reçoit `403`.

## Mourchid — moteur branché sur la plateforme

- Brancher le moteur sur l'API : file d'attente, états du déploiement (`en_file` → `construction` → `demarrage` → `en_ligne` ou `echec`), événements Reverb.
- Arrêter une copie (état `arrete`), et limiter la mémoire et le CPU de chaque copie selon sa `taille` (petite : 512 Mo et 0,5 cœur ; moyenne : 1 Go et 1 cœur ; grande : 2 Go et 2 cœurs).
- Mesurer la mémoire d'une appli après sa publication : la lancer quelques minutes, relever son pic avec `docker stats`, remplir le champ `mesure` avec la formule recommandée.
- Backend intégré : appliquer les `migrations` déclarées dans `soukdev.json`.

## Jean-Baptiste — sécurité avant publication

- Secrets : lancer gitleaks (MIT) sur le dépôt cloné, à la vérification et à la publication. Un secret trouvé : réponse `422`, publication refusée.
- `docker-compose.yml` dangereux : réutiliser la vérification écrite par Mourchid dans le moteur (semaine 1), et la brancher aussi sur `POST /apps/verification` et `POST /apps`.
- Dépendances : lancer Trivy (Apache 2.0) après la publication, en tâche de fond, et remplir le champ `securite` de l'appli (`en_cours`, puis `verifie` ou `problemes`).
- `POST /apps/{id}/securite` : relance de l'analyse par l'auteur.
- Avec Hope : le badge vert « Security Checked » ou orange « Problèmes détectés » sur la carte et la fiche d'une appli, et le rapport sur la fiche.

**Terminé quand** : un dépôt contenant un faux secret est refusé, et une appli avec une dépendance vulnérable connue reçoit le badge orange avec son rapport.
