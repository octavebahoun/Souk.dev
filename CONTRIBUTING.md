# Travailler ensemble sur Souk.dev

Ces règles valent pour tout le monde, moi compris. GitHub en impose la plupart : si une règle n'est pas respectée, la PR est simplement refusée.

## 1. Une tâche, une branche

- Jamais de commit directement sur `main`.
- Une branche par tâche, nommée `prenom/tache`. Exemple : `hope/fil-discussions`, `wasfade/lien-magique`.
- Avant de commencer, partez du `main` à jour :

```sh
git checkout main
git pull
git checkout -b prenom/tache
```

## 2. Tout passe par une PR

- Une PR = une tâche qui se relit vite. Pas de PR « tout le socle » de 3 000 lignes.
- Dans la description : ce que la PR change, et comment le tester.
- **La CI bloque** : pas de merge tant qu'elle n'est pas verte (tests, style, licences, contrat).
- **Je valide le reste** : architecture, logique, respect de `DECISIONS.md`. Je suis seul à approuver et à merger.

## 3. On ne réécrit jamais `main`

- Pas de `push --force`, pas de `rebase` de `main`, même pour corriger une erreur.
- Une erreur déjà sur `main` se corrige **par un nouveau commit**.
- Sur votre propre branche, vous pouvez réécrire (`git push --force-with-lease`), tant que personne d'autre ne travaille dessus.

## 4. Chacun son compte, chacun ses commits

- Chacun pousse depuis son PC, avec son compte GitHub et sa clé SSH. Jamais l'accès de quelqu'un d'autre.
- Vos commits portent votre nom. À vérifier une fois :

```sh
git config user.name   # votre nom
git config user.email  # l'email de votre compte GitHub
```

- Un agent (Cursor, Claude…) peut vous aider, mais **l'auteur du commit, c'est vous**. L'agent apparaît en `Co-authored-by`.
- L'accès au serveur est réservé à Oktav.

## 5. Aucun secret dans le dépôt

- On commite `.env.example`, jamais `.env`.
- Ni clé, ni mot de passe, ni token dans le code.
- Dans les tests, utilisez des valeurs visiblement factices (`valeur-de-test`), sinon GitGuardian les prend pour de vrais secrets.

## 6. L'IA se déclare

- Toute partie faite avec une IA va dans `AI_USAGE.md` : date, outil, partie concernée.
- Chacun doit pouvoir expliquer le code qu'il pousse, y compris celui qu'une IA a écrit.

## 7. Les mêmes versions partout

- PHP 8.4 et Node 24, sur vos PC comme en CI.
- Une nouvelle dépendance : vérifiez sa licence (MIT, Apache 2.0, BSD, PostgreSQL), puis ajoutez-la à `LICENSES.md`. GPL, AGPL et LGPL sont interdites.

## En cas d'urgence

Moi seul peux contourner les protections de `main`, et seulement en cas de blocage réel. Quand je le fais, je préviens l'équipe et j'explique pourquoi.
