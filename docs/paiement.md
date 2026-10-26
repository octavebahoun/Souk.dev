# Paiement, facturation et grand livre

Comment Souk.dev encaisse ses clients, paie ses devs et tient ses comptes. Code dans
`backend/app/Paiement/`, contrat dans `openapi.yaml` (tags Paiements, Revenus, Administration),
temps réel dans `REVERB.md` (`utilisateur.{id}`, `facture.modifiee`).

## Le modèle

- Le client paie **chaque mois l'hébergement** de sa copie (petite 3 500 F, moyenne 7 000 F,
  grande 13 500 F) et **le prix de l'appli** : chaque mois s'il est mensuel, une seule fois
  s'il est unique (sur la première facture).
- **Le prix de l'appli va entièrement au dev.** Souk.dev ne gagne que sur l'hébergement.
- Les frais SasPay sont en **`ADD_ON`** : le client les paie en plus, Souk.dev reçoit le
  montant plein. Les frais de reversement aux devs sont pris **sur la marge de Souk.dev**.
- Montants en **entiers FCFA** partout (jamais de flottant). La chaîne décimale exigée par
  SasPay (`"3500.00"`) n'existe que dans l'adaptateur (`Passerelles/Montants`).
- Aucun montant ne vient du navigateur : tout part de l'appli, de la formule et des prix figés
  sur l'abonnement à la commande (un changement de prix ne touche que les nouveaux clients).

## Le parcours d'un client

1. **Commande** (`POST /apps/{id}/deployments`) : un abonnement aux prix figés, la première
   facture, et une copie à l'état `attente_paiement`. Rien ne démarre.
2. **Paiement** (`POST /factures/{id}/paiement`, `Idempotency-Key`) : une session de checkout
   SasPay (`POST /checkout-sessions/`, montant, `XOF`, `return_url`, `metadata` avec la facture).
   Une session ouverte depuis moins de 30 minutes est réutilisée (double clic, retour arrière).
3. **Vérification** : **la copie ne démarre que lorsque `GET /payments/{id}/verify/` renvoie
   `SUCCESS`**, jamais sur la foi du navigateur ou d'un webhook. Trois chemins y mènent :
   le retour du navigateur (`POST /factures/{id}/verification`), le webhook, et le filet des
   15 minutes. Tous sont idempotents : vérifier deux fois ne paie pas deux fois.
4. Facture payée : écritures au grand livre, copie mise en file sur le moteur, e-mail
   « Paiement reçu », événement `facture.modifiee`.

Un paiement refusé laisse la facture à payer et la session ouverte : le client peut réessayer.
SasPay ne renvoie sur `return_url` qu'après un succès ; après un échec, le client revient de
lui-même (« Mes paiements », lien du rappel) et la page de retour revérifie.

## Le cycle mensuel (`CycleFacturation`, chaque jour à 6 h UTC)

| Moment | Ce qui se passe |
|---|---|
| 3 jours avant l'échéance | Facture de la période suivante émise, e-mail de rappel avec un lien de paiement signé (30 jours) |
| Lendemain de l'échéance | Abonnement `en_retard`, relance par e-mail |
| 7 jours après l'échéance | **Suspension** : conteneurs arrêtés (`docker compose stop`, volumes gardés), le sous-domaine affiche une page neutre « momentanément indisponible » (`GET /copie-suspendue`, rien sur la facture) |
| Paiement de tous les impayés échus | **Réactivation** : mêmes conteneurs, mêmes données, sous-domaine rendu après la sonde |
| 23 jours de suspension | Dernier avertissement par e-mail |
| 30 jours de suspension | **Arrêt définitif** (`down -v`, données supprimées), impayés annulés |
| Première facture impayée 7 jours | Commande annulée, rien n'avait démarré |

Les délais se comptent en jours de calendrier ; relancer le passage le même jour ne fait rien
de plus. Un client qui arrête sa copie arrête aussi son abonnement : plus aucune facture.

## Le webhook (`POST /api/webhooks/saspay`)

- Signature `X-Webhook-Signature` = HMAC SHA-256 de `"{X-Webhook-Timestamp}.{corps brut}"`
  avec `SASPAY_WEBHOOK_SECRET`, comparée en temps constant ; horodatage refusé au-delà de
  300 secondes (un webhook intercepté ne se rejoue pas).
- Idempotent : l'empreinte du corps est gardée, un même corps n'est traité qu'une fois.
- **Un webhook ne prouve rien** : il déclenche une revérification par l'API (paiement ou payout).
- Sans tunnel public, aucun webhook n'arrive en développement : le retour du navigateur et le
  filet des 15 minutes suffisent. Pour l'activer : `cloudflared tunnel --url http://localhost:8000`,
  puis déclarer `<URL>/api/webhooks/saspay` dans le tableau de bord SasPay.

## Le grand livre (`GrandLivre`, table `ecritures`)

Partie double : chaque opération s'équilibre (somme nulle), positif au débit, négatif au crédit.
**On ne modifie jamais une écriture** : le modèle le refuse, et PostgreSQL aussi (trigger) ;
une erreur se corrige par une écriture inverse (contrepassation).

| Compte | Sens |
|---|---|
| `saspay` | L'argent chez SasPay |
| `dev` (avec `dev_id`) | Ce que Souk.dev doit à chaque dev |
| `revenu_hebergement` | Le chiffre d'affaires de Souk.dev |
| `charge_frais_reversement` | Les frais des payouts, à la charge de Souk.dev |
| `trop_percu` | Un paiement en double, dû au client |

Exemple, une facture de 22 000 F (7 000 F d'hébergement, 15 000 F d'appli) :

| Compte | Montant |
|---|---|
| `saspay` | + 22 000 |
| `revenu_hebergement` | − 7 000 |
| `dev` (l'auteur) | − 15 000 |

Un reversement de 15 000 F (frais 380 F) : `dev` + 15 000, `saspay` − 15 000 à la demande,
puis `charge_frais_reversement` + 380, `saspay` − 380 à la vérification. Un payout refusé
contrepasse la réservation : le dev retrouve son solde.

## Les reversements aux devs (`Reversements`)

- Solde du dev = ses parts encaissées moins ses reversements (grand livre).
- Numéro Mobile Money saisi deux fois, **chiffré en base**, masqué partout (`+229 01 •• •• 34 56`),
  et inutilisable tant que le dev n'a pas cliqué « Confirmer ce numéro » sur la page ouverte par
  le lien de l'e-mail (un analyseur de liens qui ouvre la page ne confirme rien). Une session
  volée ne peut donc pas détourner l'argent.
- Tout le solde part, **chaque 1er du mois** ou **à la demande dès 5 000 F**, un seul à la fois.
- `POST /payouts/initialize/` avec une **`Idempotency-Key` gardée en base** et un bénéficiaire
  **figé** sur le reversement : une reprise envoie exactement la même requête. Un `409` de
  SasPay (requête identique en cours) se réessaie plus tard et **ne rend jamais le solde** :
  sinon le dev serait payé deux fois. `401`, `403`, `422` : rien n'a été créé, le solde est rendu.
- Vérification par `GET /payouts/{id}/verify/`, au retour du job, par webhook et toutes les 15 minutes.
- **Les reversements passent par le simulateur** tant que `REVERSEMENTS_REELS` n'est pas à `true`
  (un payout fait sortir de l'argent et exige l'IP du serveur en liste blanche chez SasPay).

## Le rapprochement

- **Toutes les 15 minutes** : les sessions encore ouvertes sont revérifiées.
- **Chaque nuit** : les transactions réussies de la veille (`GET /transactions/`, `INBOUND`)
  contre les paiements enregistrés. Un paiement réussi resté en attente ici est corrigé ; un
  écart qui demande un humain (transaction inconnue, montant différent, paiement absent chez
  SasPay) part par e-mail à `ADMIN_EMAIL`. Le compte marchand pouvant encaisser pour d'autres
  activités, seules les transactions dont la description commence par « Souk.dev » comptent.

## Remboursements et avoirs

SasPay n'a pas d'API de remboursement : l'admin rembourse depuis le tableau de bord SasPay,
puis saisit un **avoir** dans « Finances » (`POST /admin/factures/{id}/avoir`). Le grand livre
recule l'hébergement et la part du dev (le solde d'un dev déjà payé peut devenir négatif et se
compense sur ses ventes suivantes). Un paiement en double (`trop_percu`) prévient l'admin par
e-mail ; son remboursement se saisira depuis l'administration (phase 6).

## Règles SasPay appliquées

- Clé uniquement côté serveur, jamais journalisée (les journaux gardent la route, le statut et
  le code d'erreur). Les tests ne peuvent pas joindre la vraie API (`phpunit.xml`).
- `Idempotency-Key` sur les routes de Souk.dev qui déplacent de l'argent (paiement, reversement,
  avoir) et sur le payout SasPay.
- Réponses gérées : `401`, `403` (compte, IP, scope), `409`, `410` et `404` (session expirée ou
  introuvable : tentative close), `422`, `429` et `5xx` (nouvel essai plus tard).
- Aucun champ ni événement hors de leur spécification : vérifié contre leur `openapi.json`.
- Un identifiant SasPay n'entre dans une URL qu'après contrôle de son format (UUID).

## Développement et tests

- **Passerelle** : `PAIEMENT_PASSERELLE` vaut `simulee` par défaut dans `docker-compose.yml`
  (parcours réussis). `PAIEMENT_PASSERELLE=saspay docker compose up -d api worker scheduler`
  bascule sur l'API réelle : la clé fournie est sur un compte où les paiements échouent
  volontairement, c'est le terrain des parcours d'échec. Jamais le simulateur en production.
- **Simulateur** (`/simulateur/saspay/{session}`) : une page qui imite le checkout (succès,
  échec, attente), de vrais webhooks signés, et des payouts (un numéro finissant par 0000
  simule un refus du réseau).
- **Faire passer le temps** : `php artisan demo:avancer-facturation {copie} --jours=39`
  (développement seulement) montre pour de vrai un impayé, sa suspension et sa réactivation.
- Une session n'est vérifiée que par la passerelle qui l'a ouverte (changer de passerelle en
  développement ne fausse rien).

## Limites connues

- Le compte SasPay fourni affiche le nom marchand « Gentube » sur la page de paiement : un
  compte marchand au nom de Souk.dev sera nécessaire avant le lancement.
- Le remboursement d'un trop-perçu se saisira dans l'administration (phase 6).
