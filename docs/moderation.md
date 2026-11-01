# Modération — Souk.dev

Qui signale, qui décide, et avec quelles sanctions. La version publique et courte est la
[politique d'abus](/legal/abus) ; ce document est le mode d'emploi de l'équipe.

## Signaler

Le bouton « Signaler » d'une discussion, d'un message, d'une appli ou d'un événement
envoie à l'équipe : la cible, un motif (`spam`, `abus`, `hameconnage`, `minage`,
`contenu_illegal`, `autre`) et des faits vérifiables. 20 signalements par heure et par
compte ; un seul signalement ouvert par membre et par cible (sinon `409`).

Signaler une copie ou un compte (pas de bouton dans l'interface) : `POST
/api/signalements` avec `{"cible": "copie", "cible_id": <n°>, "motif": "minage",
"details": "…"}`. Le n° d'une copie figure dans `/admin/moderation`, onglet « Copies »
(colonne « #n° ») ; celui d'un compte, onglet « Comptes ». Un membre qui ne connaît que
l'adresse d'une copie (`http://{copie}.localhost`) ou le pseudo d'un compte écrit à
l'équipe avec cette référence : l'équipe retrouve le n° dans l'onglet et dépose le
signalement elle-même (les motifs urgents — minage, hameçonnage — partent en premier).

Les motifs graves — hameçonnage, minage, contenu illégal — préviennent l'équipe aussitôt
par e-mail : une copie de minage ou d'hameçonnage doit tomber vite. Les autres attendent
dans `/admin/moderation`.

## Politique d'abus automatique

Deux cas suspendent une copie sans attendre l'équipe, qui examine ensuite et la réactive si
ce n'était pas un abus :

- **Minage présumé** (`App\Jobs\SurveillerProcesseur`, toutes les 5 minutes, file
  `sondes`) : le processeur de chaque copie en ligne est relevé (`docker stats` sur sa
  machine). À 90 % ou plus de sa formule (0,5, 1 ou 2 cœurs) pendant 12 relevés de suite,
  soit une heure, la copie est suspendue avec le motif « Minage présumé ». Un relevé sous le
  seuil, ou une machine muette, remet le compte à zéro.
- **Hameçonnage signalé** : un signalement `hameconnage` sur une copie la suspend aussitôt
  (ses visiteurs se font voler leurs identifiants pendant l'examen). Un même membre ne
  déclenche que 3 suspensions automatiques par 24 heures ; au-delà, ses signalements
  attendent l'équipe comme les autres.

Dans les deux cas : même suspension que celle de l'équipe (conteneurs arrêtés, page neutre,
client prévenu par e-mail avec le motif), alerte à l'équipe, levée depuis l'onglet « Copies ».

## Examiner

`/admin/moderation`, onglet « Signalements » : les ouverts d'abord, les motifs graves en
rouge. Chaque ligne mène à sa cible quand elle a une page : la discussion (ou sa
discussion parente pour un message), la fiche d'une appli, la liste des événements,
l'adresse d'une copie (nouvel onglet), le profil d'un compte. Si la cible a été
supprimée entre-temps, le résumé le dit au lieu de casser la liste, et il n'y a pas de lien.

Règle : on examine avant d'agir (hors politique d'abus automatique ci-dessus, qui se
vérifie après coup). Un doute ? On laisse ouvert et on en parle à l'équipe, on ne suspend
jamais « dans le doute ».

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
