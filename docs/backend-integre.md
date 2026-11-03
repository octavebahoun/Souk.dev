# Backend intégré — Souk.dev

Pour une appli déclarée avec `"backend": "integre"` (migrations SQL obligatoires), la plateforme
fournit à chaque copie sa base PostgreSQL et son API PostgREST, plus la connexion des utilisateurs
de l'appli par un code à 6 chiffres reçu par SMS ou par e-mail.

## Ce que la copie reçoit

Dans son environnement (variables réservées, refusées dans le dépôt) :

- `SOUKDEV_URL` : l'API PostgREST (`http://soukdev-api:3000`), clé `SOUKDEV_CLE` (rôle lecture seule).
- `SOUKDEV_AUTH_URL` : la connexion des utilisateurs (`/api/copies/{cle}/auth`), clé publique
  `SOUKDEV_CLE_PUBLIQUE` (aussi dans l'URL).

## Connexion des utilisateurs

1. L'appli appelle `POST {SOUKDEV_AUTH_URL}/code` avec `canal` (`sms` ou `email`) et
   `destinataire` (numéro au format international, adresse en minuscules). Réponse `202` :
   `expire_dans` (600 secondes). Trois codes par quart d'heure et par destinataire.
2. L'utilisateur saisit son code : `POST {SOUKDEV_AUTH_URL}/session` rend un `jeton` JWT (un jour,
   rôle `soukdev_utilisateur`, identifiant stable dans `sub`) et l'utilisateur (destinataire
   masqué). Cinq essais par code, un code ne sert qu'une fois.
3. L'appli appelle PostgREST avec `Authorization: Bearer {jeton}`.

Contrat : `/copies/{cle}/auth/code`, `/copies/{cle}/auth/session` dans `openapi.yaml`. Ces routes
acceptent toute origine (l'appli les appelle depuis son propre domaine), jamais avec les cookies
Souk.dev. Sans fournisseur de SMS configuré, `SmsSimule` garde le message une heure, lisible en
développement sur `/simulateur/sms`. Les numéros et adresses sont chiffrés au repos, les codes
hachés ; les journaux n'ont que des numéros masqués.

## Côté base

Les migrations du dev tournent après la création du rôle `soukdev_utilisateur` et de
`soukdev.utilisateur_id()` (le `sub` du jeton). Pour cloisonner les lignes par utilisateur :

```sql
ALTER TABLE notes ENABLE ROW LEVEL SECURITY;
CREATE POLICY notes_lecture ON notes FOR SELECT TO soukdev_utilisateur
    USING (utilisateur = soukdev.utilisateur_id());
```

Le registre `soukdev.migrations` (hors du schéma exposé) fait qu'une mise à jour n'applique que les
nouvelles migrations. Sauvegardes et export : voir « Les sauvegardes » dans
`docs/mises-a-jour.md`.

## Démo

Carnet (`database/seeders/demo/depots/carnet`, appli du jeu de démo) : un carnet de commandes en
HTML seul, nginx servant le site et relayant `/donnees/` vers PostgREST. Le parcours
`frontend/e2e/backend-integre.spec.ts` y connecte deux utilisateurs par des codes SMS simulés,
vérifie que chacun ne voit que ses notes, puis sauvegarde et exporte la copie.
