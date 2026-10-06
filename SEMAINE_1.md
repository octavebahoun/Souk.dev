# Semaine 1 — du 6 au 12 octobre

Objectif : chacun peut avancer sans attendre les autres. Le front code contre une fausse API, le back contre le contrat, le moteur tourne en local.

Règle de merge : **la CI bloque, Oktav valide le reste** (voir [`DECISIONS.md`](DECISIONS.md#revue-et-merge)).

## Dépendances

- Le **squelette** d'Oktav passe en premier : Hope en a besoin pour la fausse API et les types, Wasfade pour le projet Laravel.
- Le **moteur** de Mourchid ne dépend de personne : il peut commencer tout de suite.
- La **CI** de Jean-Baptiste s'appuie sur le squelette (dossiers `frontend/` et `backend/`).

## Oktav — architecture, squelette, revue

- Créer `frontend/` : React + Vite, avec toute l'arborescence.
- Créer `backend/` : Laravel, avec toute l'arborescence.
- Générer les types TypeScript depuis `openapi.yaml` (`openapi-typescript`).
- Lancer une fausse API à partir de `openapi.yaml` avec **Prism** (Apache 2.0).
- Revue complète du backend et du frontend : vérifier, corriger, merger les branches.
- Coordonner l'équipe au fil des tâches.

**Terminé quand** : `frontend/` et `backend/` démarrent, les types sont générés, la fausse API répond aux routes du contrat.

## Hope — écrans sur la fausse API

- Fil des discussions.
- Page d'une discussion, avec la démo dans son cadre isolé.
- Puis tous les autres écrans, sans limitation côté frontend.

**Terminé quand** : le fil et la page d'une discussion fonctionnent sur la fausse API, avec les types générés.

## Mourchid — moteur en local

- Cloner un dépôt GitHub.
- Valider `soukdev.json` avec `soukdev.schema.json`.
- Générer le `.env` de la copie (sources client, plateforme, fixe).
- Lancer avec `docker compose -p <copie>`.
- Préparer l'appli cobaye avec Jean-Baptiste.

**Terminé quand** : à partir d'un lien GitHub, l'appli cobaye tourne en local, isolée dans son propre projet compose.

## Wasfade — socle Laravel

- Sanctum (auth SPA).
- Connexion GitHub (Socialite).
- Lien magique par email.
- Tables : comptes, discussions, messages.
- Reverb démarré.

**Terminé quand** : on se connecte (GitHub ou lien magique), on crée une discussion et un message, et les réponses respectent `openapi.yaml`.

## Jean-Baptiste — CI, cobaye, reverse proxy

- CI : tests, licences, respect du contrat.
- Appli cobaye, avec Mourchid.
- Reverse proxy : un sous-domaine par copie, seul `service_web` exposé.

**Terminé quand** : une PR qui casse un test, ajoute une licence interdite ou s'écarte du contrat est bloquée ; le reverse proxy sert l'appli cobaye sur un sous-domaine.
