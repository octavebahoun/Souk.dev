# Décisions — Souk.dev

Décisions prises par l'équipe, validées par Oktav. Mis à jour au fil du projet.

## Fil de la démo

**L'entraide rend l'appli vendable.** Les devs d'abord : le jury voit un dev qui gagne grâce à sa communauté, pas un client qui fait ses courses.

1. Un dev poste un bug (ex. « mon paiement MoMo plante ») et partage une copie de test de son appli.
2. Un autre dev ouvre la copie, corrige, et déploie sa version corrigée. L'auteur la voit tourner et accepte le correctif (entraide gratuite).
3. L'appli marche : le dev la publie dans le store (ex. 15 000 F/mois).
4. Une clinique lui demande une version adaptée : mission sur mesure, sans enchères.

Le dev gagne de l'argent uniquement via le prix de ses applis et les missions sur mesure. Corriger un bug dans l'échange n'est pas rémunéré.

## Ordre de construction

1. **Moteur de déploiement** : la partie la plus risquée, et le Store comme l'Échange en dépendent.
2. **Espace d'échange** : cœur du thème, avec la copie de test lancée par le moteur.
3. **Publier + Store**.

## Stack

| Couche | Choix | Licence |
|---|---|---|
| Backend | Laravel | MIT |
| Temps réel | Laravel Reverb + Laravel Echo | MIT |
| Frontend | React + Vite, SPA séparée | MIT |
| Auth SPA ↔ API | Laravel Sanctum | MIT |

Le frontend est une SPA séparée qui appelle l'API Laravel : le front (JS) et le back (PHP) avancent chacun de leur côté, en se mettant d'accord sur le format des réponses de l'API.

Le **contrat d'API** (routes et format des réponses) est écrit par Oktav, au format **OpenAPI** (`openapi.yaml`). Ce fichier sert à :
- le front, pour coder contre une fausse API en attendant le back ;
- le back, pour savoir exactement quoi renvoyer ;
- les tests DAST de Jean-Baptiste, qui peuvent être lancés à partir de ce fichier.

Les **événements temps réel** (Reverb) sont décrits à part dans `REVERB.md`, car OpenAPI ne couvre pas les WebSockets. Leurs payloads reprennent les schémas de `openapi.yaml`.

Écarté :
- microservice Node / Socket.IO (deuxième auth et deuxième déploiement, code non maîtrisé par l'équipe backend) ;
- Inertia (le front devrait coder dans le projet Laravel et dépendrait des contrôleurs PHP).

## Organisation du code

Un seul dépôt : `frontend/` (React) + `backend/` (Laravel).

**Types partagés** : `openapi.yaml` est la seule source.
- Front : types TypeScript générés avec `openapi-typescript` (MIT). Si le contrat change, on régénère et TypeScript signale ce qui casse.
- Back : pas de génération ; les tests vérifient que chaque réponse respecte le contrat avec `osteel/openapi-httpfoundation-testing` (MIT). La CI bloque tout écart.

## Contrat d'API : ressources du MVP

On liste uniquement les routes du MVP, ressource par ressource : **Comptes**, **Applis**, **Déploiements**, **Discussions** (avec canaux et étiquettes), **Correctifs**, **Événements**.

### Comptes

- Connexion **GitHub uniquement** (Laravel Socialite, MIT). Indispensable : publier une appli, c'est lier un dépôt GitHub.
- Le profil du dev affiche ses dépôts, leurs étoiles et leurs README, récupérés via GitHub.
- **Dépôts publics uniquement** pour le MVP : on ne demande jamais la permission `repo`, qui donne lecture et écriture sur tout le code du dev. Un jeton qui fuit ne peut donc pas modifier son code.
- Routes : `GET /auth/github`, `GET /auth/github/callback`, `POST /logout`, `GET /me`, `GET /devs/{username}` (profil public).
- Écarté pour le MVP : connexion Google (pas de dépôts, d'étoiles ni de README ; une connexion de plus à coder et sécuriser).

### Applis

- Routes : `GET /apps` (le store), `GET /apps/{id}` (détail : README, démo, prix, auteur), `POST /apps` (publier : dépôt, lien de démo, prix), `PATCH /apps/{id}` (modifier), `DELETE /apps/{id}` (retirer).
- `POST /apps` **valide `soukdev.json` immédiatement** et refuse la publication s'il est invalide : le dev voit l'erreur en publiant, pas un client en déployant.

### Déploiements

- Routes : `POST /apps/{id}/deployments` (lancer, avec les variables du client), `GET /deployments/{id}` (état et URL), `GET /deployments` (mes déploiements), `DELETE /deployments/{id}` (arrêter).
- Un déploiement dure plusieurs minutes : le `POST` répond **tout de suite** avec l'état `en_file`, le travail tourne en tâche de fond (file d'attente Laravel).
- Reverb pousse chaque changement d'état au front : `en_file` → `construction` → `demarrage` → `en_ligne` ou `echec`.

### Discussions (en cours)

- Routes : `GET /discussions` (le fil), `POST /discussions` (poster un bug, appli jointe en option), `GET /discussions/{id}`, `POST /discussions/{id}/messages` (répondre).
- **Une seule ressource** pour l'échange central et la discussion propre à chaque appli : filtre `GET /discussions?app=42`.
- **Canaux** : une discussion appartient à un canal (ex. `#laravel`, `#mobile-money`) ou à une appli. Même mécanisme de filtre : `GET /discussions?canal=laravel`. Routes : `GET /canaux`, `POST /canaux`.
- **Sujets** : des étiquettes posées sur une discussion (ex. `bug`, `question`, `tuto`). Filtre : `GET /discussions?etiquette=bug`.

### Correctifs

- Routes : `POST /discussions/{id}/correctifs` (proposer : lien vers un fork ou une branche GitHub), `GET /discussions/{id}/correctifs`, `POST /correctifs/{id}/accepter`.
- Un correctif est **un dépôt que le moteur déploie** : on réutilise la ressource Déploiements, l'auteur voit la version corrigée tourner.
- Pas de permission `repo` : « accepter » ne fusionne rien, l'auteur fusionne lui-même sur GitHub.

### Événements

Rencontre avec date et inscription, en ligne (avec lien) ou en présentiel (avec lieu).

- Routes : `GET /events`, `POST /events` (titre, date, type, lieu ou lien), `GET /events/{id}`, `POST /events/{id}/inscription`, `DELETE /events/{id}/inscription`, `GET /events/{id}/participants`, `POST /events/{id}/avis`.
- Un avis ne peut être laissé que par un **inscrit**, et seulement **après la date** de l'événement.
- Le lien en ligne n'est visible **que par les inscrits**.

### Formats de données

- Prix d'une appli : **entier en FCFA**, par mois ; `0` = gratuit.
- Avis d'un événement : **note de 1 à 5**, commentaire optionnel.
- **Pagination** par numéro de page (`?page=2&per_page=20`, 50 maximum), au format natif des API Resources Laravel : la liste dans `data`, plus `links` et `meta`. Concerne les applis, déploiements, discussions, correctifs, événements et participants.

## Rôles

| Membre | Rôle |
|---|---|
| Oktav | Frontend React + coordination |
| Hope | Frontend React |
| Mourchid | Backend Laravel : moteur de déploiement, puis Publier + Store une fois le moteur stable |
| Wasfade | Backend Laravel : API Échange, Reverb, comptes utilisateurs |
| Jean-Baptiste | CI/CD, SAST, DAST, sécurité, tests ; vérification automatique des licences en CI (`composer licenses`, `license-checker`) ; appui Docker et VPS pour le moteur |

## Moteur de déploiement

- **Objectif** : on lui donne un lien GitHub, il rend une URL où l'appli tourne.
- **Étape 1** : cloner le dépôt, lire et **valider** `soukdev.json` (fichier non fiable, écrit par un dev inconnu).
- **Étape 2** : générer un `.env` par client. Le dev déclare chaque variable dans `soukdev.json` avec sa source :
  - remplie par le client (ex. `PHARMACIE_NOM`) ;
  - générée par la plateforme (ex. `DB_PASSWORD`) ;
  - valeur fixe choisie par le dev (ex. `DEVISE=FCFA`).
- **Étape 3** : lancer avec `docker compose -p <client>` (réseau, volumes et conteneurs isolés par client), derrière un reverse proxy avec un sous-domaine par client (ex. `pharma-a.soukdev.com`).
- **Machine cible** : le moteur déploie sur « une machine » sans savoir laquelle. VPS partagé pour la démo, un VPS par client si Datacloud fournit une API de création.

## Points ouverts

- Datacloud permet-il de créer des VPS ou des conteneurs par API ? (question posée à Systalink, en attente)
- Appli cobaye pour tester le moteur : à créer plus tard.
- Vérifier que l'appli est vraiment prête avant de donner son URL (étape 4 du moteur).
