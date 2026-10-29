# Modération — Souk.dev

Qui signale, qui décide, et avec quelles sanctions. La version publique et courte est la
[politique d'abus](/legal/abus) ; ce document est le mode d'emploi de l'équipe.

## Signaler

Le bouton « Signaler » d'une discussion ou d'une appli envoie à l'équipe : la cible, un
motif (`spam`, `abus`, `hameconnage`, `minage`, `contenu_illegal`, `autre`) et des faits
vérifiables. 20 signalements par heure et par compte ; un seul signalement ouvert par
membre et par cible (sinon `409`).

Les motifs graves — hameçonnage, minage, contenu illégal — préviennent l'équipe aussitôt
par e-mail : une copie de minage ou d'hameçonnage doit tomber vite. Les autres attendent
dans `/admin/moderation`.

## Examiner

`/admin/moderation`, onglet « Signalements » : les ouverts d'abord, les motifs graves en
rouge. Chaque ligne mène à sa cible quand elle a une page (discussion, appli, événement).
Si la cible a été supprimée entre-temps, le résumé le dit au lieu de casser la liste.

Règle : on examine avant d'agir. Un doute ? On laisse ouvert et on en parle à l'équipe,
on ne suspend jamais « dans le doute ».

## Sanctions

- **Supprimer un contenu** : les routes existantes (l'auteur ou l'admin supprime une
  discussion, un message, un événement). Puis marquer le signalement traité, avec une note.
- **Suspendre une copie pour abus** (`/admin/moderation`, onglet « Copies », ou `POST
  /admin/deployments/{id}/suspension` avec un motif) : seulement si elle est en ligne.
  Conteneurs arrêtés par le moteur, page neutre sur le sous-domaine (rien sur le motif :
  les visiteurs n'ont pas à le savoir), volumes gardés. Le client est prévenu par e-mail
  (toujours envoyé), avec le motif et un moyen de contester.
- **Suspendre un compte** (`POST /admin/comptes/{id}/suspension`) : plus de connexion
  (GitHub, lien magique, démo) ni d'API, sauf la lecture de son propre compte (pour voir
  son motif) et la déconnexion. L'équipe ne suspend jamais son propre compte.
- **Canaux** : renommer ou supprimer un canal vide (refusé, `409`, tant que des
  discussions l'utilisent). Le slug ne change jamais : les liens restent valables.

La suspension pour abus ne touche pas la facturation : une copie suspendue pour impayé ne
repart qu'au paiement, et une copie suspendue pour abus reste suspendue tant que l'équipe
ne lève pas la sanction, même à jour de paiement.

## Lever

- Copie : « Réactiver » (ou `DELETE …/suspension`) — les mêmes conteneurs repartent avec
  leurs données. Refusé si la copie est aussi suspendue pour impayé.
- Compte : « Lever » — le compte agit de nouveau aussitôt.

En cas d'erreur reconnue, rétablir sans attendre et le noter sur le signalement.

## Contestation

Chaque suspension part avec un e-mail qui donne le motif : il suffit d'y répondre pour
contester. Les données restent gardées pendant l'examen. Les signalements non fondés sont
rejetés, avec une note ; un membre qui signale abusivement s'expose à une suspension.
