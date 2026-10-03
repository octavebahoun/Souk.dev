# Décisions — Souk.dev

Décisions prises par l'équipe, validées par Oktav. Mis à jour au fil du projet.

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

Écarté :
- microservice Node / Socket.IO (deuxième auth et deuxième déploiement, code non maîtrisé par l'équipe backend) ;
- Inertia (le front devrait coder dans le projet Laravel et dépendrait des contrôleurs PHP).

## Rôles

| Membre | Rôle |
|---|---|
| Oktav | Frontend React + coordination |
| Hope | Frontend React |
| Mourchid | Backend Laravel : moteur de déploiement, puis Publier + Store une fois le moteur stable |
| Wasfade | Backend Laravel : API Échange, Reverb, comptes utilisateurs |
| Jean-Baptiste | CI/CD, SAST, DAST, sécurité, tests ; appui Docker et VPS pour le moteur |

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
