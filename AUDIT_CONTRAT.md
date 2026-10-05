# Audit du contrat — 2026-10-05

Comparaison de `openapi.yaml` et `REVERB.md` avec `DECISIONS.md` et le brief.
Les propositions de correction sont **à valider par Oktav**. Rien n'est encore corrigé.

## A. Contradictions avec nos décisions (bloquant)

1. **Les clients ne peuvent pas se connecter.** La connexion se fait uniquement par GitHub, mais une pharmacie ou une clinique n'a pas de compte GitHub. Or c'est elle qui déploie et qui demande une mission.
2. **La démo commence par une appli non publiée.** Étape 1 du fil de la démo : le dev partage la copie de test d'une appli qui n'est pas encore dans le store. Mais `POST /discussions` n'accepte qu'un `app_id`, donc une appli déjà publiée. Il faut pouvoir joindre un simple dépôt GitHub.
3. **Le format de `soukdev.json` n'est défini nulle part.** `POST /apps` doit le valider, mais aucun document ne dit ce qu'il contient. Mourchid ne peut pas coder la validation, et les devs ne savent pas l'écrire.
4. **Les missions sur mesure n'existent pas.** Étape 4 du fil de la démo (une clinique demande une version adaptée) : aucune route. À décider : est-ce une discussion sur l'appli (`?app=42`) ou une ressource à part ?

## B. Sécurité

5. **CSRF Sanctum absent.** La SPA doit appeler `GET /sanctum/csrf-cookie` puis envoyer l'en-tête `X-XSRF-TOKEN`. Ni la route ni l'erreur `419` ne sont décrites.
6. **Aucune limite de débit.** Rien n'empêche un compte de lancer 500 déploiements et de saturer le VPS. Il faut une limite (surtout sur `POST /apps/{id}/deployments` et `POST /discussions/{id}/correctifs`) et la réponse `429`.
7. **Autorisation des canaux privés Reverb non décrite.** Echo appelle `POST /broadcasting/auth` pour `deploiement.{id}` ; cette route n'apparaît nulle part.
8. **Routes GitHub mal placées.** `/auth/github` et `/auth/github/callback` sont déclarées sous `/api`, alors que Laravel les met dans les routes web.
9. **Nom du cookie de session** encore en `TODO`.
10. **Pas de modération.** Impossible de supprimer un message ou une discussion abusive, ni pour l'auteur, ni pour un admin.

## C. Routes manquantes

11. `GET /events/{id}/avis` : on peut laisser un avis mais pas les lire.
12. `PATCH` et `DELETE /events/{id}` : l'organisateur ne peut ni corriger la date ni annuler.
13. Modifier ou supprimer sa discussion ou son message.
14. Recherche et filtres dans le store (`GET /apps?q=…`).
15. Liste des étiquettes : libres ou fixes ? Si fixes, il faut `GET /etiquettes`.

## D. Règles métier non décrites

16. **Doublons** : deux avis du même dev, deux inscriptions, accepter deux fois un correctif. Il manque la réponse `409`.
17. **État `arrete`** : après `DELETE /deployments/{id}`, l'énumération `etat` n'a pas d'état pour un déploiement arrêté.
18. **Canal ou appli** : une discussion appartient à un canal **ou** à une appli, mais le contrat autorise les deux à la fois.
19. **Qui voit les participants** d'un événement : décision non prise.
20. **Messages non paginés** dans `GET /discussions/{id}` : une longue discussion renverra tout d'un coup.

## E. Détails

21. `DECISIONS.md` marque encore la ressource Discussions « (en cours) ».
22. La description de `openapi.yaml` dit encore « squelette ».
