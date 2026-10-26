# Mises à jour poussées

Le dev publie une nouvelle version de son appli ; chaque client choisit quand l'appliquer à sa
copie ; une version qui ne démarre pas revient d'elle-même à la précédente, avec ses données.
Code : `VersionController`, `VerifierVersion`, `MiseAJourCopie` (moteur), `MettreAJourCopie`,
`SauvegardeVolumes` ; contrat : `/apps/{id}/versions`, `/deployments/{id}/mise-a-jour`.

## Les versions

- **Version 1** : la publication. Son commit est lu au moment de la vérification du dépôt.
- **Version suivante** : l'auteur la publie depuis la fiche (« Publier une nouvelle version ») avec
  ce qui change pour ses clients et, au choix, une branche, un tag ou un commit (par défaut la
  branche publiée). Elle passe les mêmes contrôles qu'une publication, sur ce code précis :
  `soukdev.json`, `docker-compose.yml`, aucun secret (gitleaks). Publiée, elle garde son commit ;
  refusée, l'auteur lit pourquoi. Une seule version en vérification à la fois.
- **Chaque copie est épinglée** sur le commit de sa version : une nouvelle copie part de la dernière
  version publiée, et une copie ne change jamais de code sans que son client l'ait demandé
  (« ce que contient la branche aujourd'hui » ne compte pas).
- Une version publiée prévient les clients des copies en ligne (notification « Mises à jour de vos
  copies », e-mail compris) et relance l'analyse de sécurité de l'appli.
- Le journal des versions est public sur la fiche ; l'auteur y voit aussi ses versions refusées.

## Appliquer une version (le client)

Depuis « Mes copies » : « Version N disponible » → « Mettre à jour ». Si la version demande une
nouvelle information au client (variable `client` obligatoire), la fenêtre la demande. Puis, sur
le moteur :

1. Le nouveau code est préparé à côté : clone au commit, contrôles, `.env` qui **garde** les secrets
   générés (le mot de passe d'une base ne change pas sous ses données) et les valeurs du client ;
   un backend intégré garde sa base, son secret et sa clé. Refusé ici, rien ne s'arrête.
2. Les conteneurs s'arrêtent, **chaque volume est sauvegardé** (`tar.gz` dans
   `storage/app/private/sauvegardes/d{id}/{horodatage}/`).
3. Le nouveau code prend la place de l'ancien ; les conteneurs sont reconstruits sur les mêmes
   volumes ; les **nouvelles** migrations du backend intégré passent (registre
   `soukdev.migrations` dans la base de la copie, hors du schéma exposé par PostgREST) ; la sonde
   doit répondre ; la route revient.
4. Sinon, **retour arrière** : ancien code, volumes restaurés, anciens conteneurs, même sonde.

Issues : `reussie` (la copie tourne sur la nouvelle version), `retablie` (la nouvelle version a été
refusée ou n'a pas démarré : la copie tourne sur la précédente, avec ses données, et le client lit
pourquoi), `echouee` (le retour arrière lui-même a échoué : la copie est en échec, l'équipe est
prévenue avec l'emplacement de la sauvegarde). Le client est prévenu dans tous les cas.

La copie est indisponible le temps de la mise à jour (quelques secondes pour une appli légère, plus
pour une image à reconstruire) ; le client choisit le moment.

## Les sauvegardes

Un conteneur jetable de l'image PostgreSQL déjà utilisée lit ou écrit chaque volume, sans réseau ;
l'archive passe par une redirection du shell du moteur, jamais par la mémoire de PHP. Les
sauvegardes de plus de 7 jours sont retirées chaque nuit, la dernière de chaque copie toujours
gardée (`MOTEUR_SAUVEGARDES_JOURS`).

## Démo

Le miroir local de PharmaStock a deux branches en plus de `main` : `v2` (un bandeau « Nouveau dans
la version 2 ») et `casse` (un serveur qui s'arrête au démarrage). Le parcours
`frontend/e2e/mises-a-jour.spec.ts` publie la première, l'applique, puis publie la seconde et
vérifie le retour arrière sur de vrais conteneurs.
