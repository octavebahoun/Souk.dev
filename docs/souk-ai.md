# Souk AI

L'analyse IA d'un bug, à côté des correctifs de la communauté (DECISIONS.md, ajout 6). Code dans
`backend/app/Ia/`, `AnalyseIaController`, `AnalyserBug` ; contrat : `POST /discussions/{id}/analyse-ia`
et le champ `analyse_ia` de la discussion ; temps réel : `analyse_ia.terminee` sur `discussion.{id}`.

## Le parcours

1. Sur une discussion avec l'étiquette `bug`, **seul son auteur** voit « Demander l'avis de Souk AI ».
2. Une fenêtre dit ce qui part chez DeepSeek (titre, description, erreur, comportement attendu, code)
   et que l'analyse sera publique. Rien ne part sans la case cochée : l'API exige `consentement: true`
   (422 sinon) et garde la date du consentement.
3. `202` : l'analyse part sur la file `ia` (son propre worker, jamais devant les paiements). Le
   travail appelle DeepSeek, enregistre le texte et diffuse `analyse_ia.terminee` (sans le texte :
   la page relit la discussion) ; la page se met à jour sans recharger (relecture toutes les 4 s
   sans Reverb) et l'annonce aux lecteurs d'écran.
4. La réponse (Markdown : cause probable, correction proposée, pour vérifier) est rendue sans HTML
   brut, visible de tous. Elle ne résout jamais la discussion ; l'auteur peut la relancer. Une
   relance qui échoue laisse la dernière bonne analyse affichée.
5. **Modifier la fiche du bug périme ses analyses** : elles disparaissent, et un résultat qui
   arrive après la modification est écarté. L'auteur en redemande une sur la fiche à jour.

## Limites et refus

| Cas | Réponse |
|---|---|
| Pas connecté | 401 |
| Pas l'auteur | 403 |
| Pas d'étiquette `bug`, pas de consentement | 422 |
| Une analyse de ce bug déjà en cours (depuis moins de 10 minutes) | 409 |
| Plus de 3 demandes en 24 heures, ou plus de 6 tentatives | 429, avec `Retry-After` et le délai avant la prochaine |
| File des travaux injoignable | 503 (la demande ne compte pas) |

La limite est glissante (24 heures depuis la plus ancienne) et se vérifie sous le verrou du compte :
deux demandes simultanées ne passent pas toutes les deux. Tout compte, même une analyse dont la
discussion a été supprimée ou dont la fiche a changé, et même une réponse refusée ou coupée
(sinon une fiche qui pousse le modèle à l'échec offrirait des appels sans limite) ; seules les
pannes du fournisseur ou de la file sont rendues, et six tentatives au plus par 24 heures.
Une analyse restée « en cours » plus de 10 minutes (travail perdu) est affichée « indisponible »
et ne bloque plus rien ; le planificateur la clôt.

## Le fournisseur

- **API officielle DeepSeek** (`https://api.deepseek.com/chat/completions`, format OpenAI), modèle
  `deepseek-flash` (DeepSeek-V4.1-Flash, lu dans `GET /models` le 26 octobre 2026), effort de
  raisonnement `high`, 6 000 jetons au plus (raisonnement compris), 90 secondes au plus par appel.
  Réglage : `DEEPSEEK_MODELE`.
- Derrière l'interface `FournisseurIa` : un autre fournisseur se branche sans toucher au reste.
  `IaSimulee` sert aux tests et au travail hors ligne (`IA_FOURNISSEUR=simulee`, ou clé absente en
  développement) ; il est refusé en production.
- **Panne** (réseau, délai, 429, 5xx, ressources insuffisantes) : un nouvel essai 30 s plus tard,
  puis « analyse indisponible ». **Refus** (réponse vide, filtrée ou coupée ; clé, solde, modèle ou
  paramètre refusés) : « analyse indisponible » tout de suite ; un refus qui vient de la
  configuration prévient l'équipe (`ADMIN_EMAIL`), une fois par heure au plus.

## Ce qui part, ce qui reste

- La consigne fixe traite la fiche comme des **données, jamais des instructions**, entre deux bornes
  tirées au hasard à chaque appel (l'auteur ne peut pas fermer le bloc pour glisser ses consignes).
  Chaque champ est tronqué (titre 200, description 3 000, code 6 000 caractères).
- Les secrets repérés sont **masqués avant l'envoi** : clés de paiement, `sk-…`, AWS, GitHub,
  Slack (jetons et webhooks), Google, SendGrid, Telegram, JWT, clés privées même coupées,
  identifiants dans une adresse (`postgres://…:…@`), `Bearer …`, et toute variable dont le nom dit
  clé, secret, jeton ou mot de passe (`.env`, tableaux PHP, JSON, en-têtes).
- Les journaux gardent le statut HTTP, le modèle et la consommation ; jamais la clé, la consigne ni
  le code. Chaque demande garde ses jetons (suivi du coût).

## Coût

Une analyse lit environ 400 jetons et en écrit environ 1 000 (raisonnement compris), 6 000 au plus :
quelques millièmes de dollar au tarif de DeepSeek. Six tentatives par 24 heures et par compte, au
plus, bornent la dépense ; la consommation de chaque demande est gardée.
