# Semaine 2 — du 13 au 19 octobre

En préparation : les tâches de chacun seront ajoutées après le bilan de la semaine 1.

## Mourchid — moteur branché sur la plateforme

- Brancher le moteur sur l'API : file d'attente, états du déploiement (`en_file` → `construction` → `demarrage` → `en_ligne` ou `echec`), événements Reverb.
- Arrêter une copie (état `arrete`), et limiter la mémoire et le CPU de chaque copie.
- Backend intégré : appliquer les `migrations` déclarées dans `soukdev.json`.

## Jean-Baptiste — sécurité avant publication

- Secrets : lancer gitleaks (MIT) sur le dépôt cloné, à la vérification et à la publication. Un secret trouvé : réponse `422`, publication refusée.
- `docker-compose.yml` dangereux : réutiliser la vérification écrite par Mourchid dans le moteur (semaine 1), et la brancher aussi sur `POST /apps/verification` et `POST /apps`.
- Dépendances : lancer Trivy (Apache 2.0) après la publication, en tâche de fond, et remplir le champ `securite` de l'appli (`en_cours`, puis `verifie` ou `problemes`).
- `POST /apps/{id}/securite` : relance de l'analyse par l'auteur.
- Avec Hope : le badge vert « Security Checked » ou orange « Problèmes détectés » sur la carte et la fiche d'une appli, et le rapport sur la fiche.

**Terminé quand** : un dépôt contenant un faux secret est refusé, et une appli avec une dépendance vulnérable connue reçoit le badge orange avec son rapport.
