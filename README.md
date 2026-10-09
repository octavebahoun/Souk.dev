# Souk.dev — Brief projet (CADEV 2026)

Document de référence pour démarrer la cartographie, l'architecture et le développement. Il contient tout ce qui a été décidé, les contraintes du concours, et les idées déjà écartées.

---

## 1. Contexte du concours

**Concours** : CADEV, Coupe d'Afrique des Développeurs, organisée par Systalink Sénégal (plateforme cloud Datacloud).

**Thème officiel** : « Construire la plateforme d'échange africaine, pour les devs, par les devs. Livrez un projet utilisable et hébergé en ligne. »

**Livrables** : dépôt git + démo en ligne.

**Équipe** : Excellence Team, pays de représentation Bénin. Équipes de 2 à 5 membres. Confirmés : Oktav (stack, architecture, encadrement) et Mourchid. Trois places restantes, chaque profil validé par Oktav.

**Calendrier**
- Le tableau de bord indique une fin des soumissions au **25 octobre 2026**. Le règlement indique le 31 octobre 2026 à 17h (heure de Dakar). **Objectif interne : 25 octobre.** Confirmation demandée à cadev@systalink.com.
- Vote des pairs : 1er au 2 novembre 2026. **Chaque membre de l'équipe doit voter, sinon le projet est exclu.**
- Vote du public et jury : 4 au 10 novembre 2026.
- Résultats : 15 novembre 2026.

**Prix** : 5 000 000 FCFA brut. En cas de victoire, cession exclusive de la solution à Systalink (code, bases, docs, graphismes). L'équipe ne peut plus l'exploiter ensuite. Si on perd, on garde tous nos droits.

---

## 2. Contraintes du règlement à respecter pendant le dev

- **Projet neuf** : codé pour l'essentiel pendant le concours. Aucun code de Contravo, Gentube ou autre produit Excellence Team. Du code personnel antérieur est toléré s'il est déclaré, mais à éviter.
- **Licences** : uniquement des composants sous licence permissive (MIT, Apache 2.0, BSD, licence PostgreSQL). **Interdits : GPL, AGPL, LGPL** (le projet devient irrecevable). Exemple : MinIO est sous AGPL, donc exclu.
- **Tenir dès le départ un tableau `LICENSES.md`** : composant, version, licence.
- **IA** : autorisée mais à déclarer (outils utilisés, parties concernées). Le jury évalue la maîtrise du code, y compris celui généré par IA. **Tenir un fichier `AI_USAGE.md`** mis à jour au fil du projet. Chaque membre doit pouvoir expliquer son code.
- **Datacloud obligatoire** : le déploiement doit utiliser au moins un produit Datacloud payant (remise de 40 % avec le code promo de l'équipe).
- **Déclaration de titularité** : projet réalisé hors de toute mission employeur, client ou école.

**Grille du jury**
| Critère | Poids |
|---|---|
| Pertinence et utilité au regard du thème | 25 % |
| Qualité technique (architecture, robustesse, sécurité, code, maîtrise, licences) | 30 % |
| Expérience utilisateur et ergonomie | 20 % |
| Innovation et originalité | 15 % |
| Vote du public (bonus plafonné) | 10 % |

---

## 3. Le concept en une phrase

Souk.dev (soukdev.com) est le marché des devs africains : ils y publient leurs applis, n'importe qui peut les déployer en un clic sur Datacloud, et un espace d'échange entre devs leur ouvre entraide concrète, missions et revenus.

Positionnement : **les devs d'abord**. Ce sont eux qui publient, échangent, corrigent et gagnent. Les entreprises clientes sont un débouché pour les devs, pas le cœur du produit.

---

## 4. Les acteurs

- **Dev auteur** : publie une appli, fixe son prix (ou la publie gratuitement), répond aux demandes.
- **Dev contributeur** : participe aux échanges, aide à corriger, propose des améliorations.
- **Client** (entreprise, ex. une pharmacie) : parcourt le store, teste la démo, déploie une appli, paie, peut demander une version sur mesure.

---

## 5. Les modules

### 5.1 Le store
- Le dev publie une appli en liant son **dépôt GitHub**.
- Le dépôt contient :
  - un `docker-compose.yml` décrivant front, back et base ;
  - un fichier de description `soukdev.json` (nom provisoire) qui liste :
    - les variables que le client remplit au déploiement (ex. nom de la pharmacie) ;
    - les variables générées automatiquement par la plateforme (ex. mot de passe de la base) ;
    - le type de backend utilisé (propre ou intégré).
- Le dev ajoute un **lien de démo** pour que le client voie l'appli et valide le design avant de payer.
- Le dev fixe un **prix** (ex. 15 000 F/mois) ou publie gratuitement.
- Le store ressemble à un app store, mais pour des applis web complètes.

### 5.2 Le déploiement en un clic
- Le client clique sur « Déployer ».
- La plateforme construit l'appli à partir du dépôt et lance **une copie neuve et isolée par client** sur Datacloud, avec ses propres données.
- Le client ne voit jamais la technique (pas de clés, pas de configuration).
- Une version déjà déployée par le dev ne sert pas de base : c'est le dépôt qui sert de source, pour pouvoir créer autant de copies que de clients.
- Quand le dev pousse une mise à jour sur GitHub, la plateforme peut la proposer à tous les clients.

### 5.3 Le backend intégré (optionnel)
- Pas obligatoire : un dev peut publier avec son propre backend.
- Si le dev le choisit, il le fait **dès la création de son projet**, comme avec Supabase : il crée un backend de test sur la plateforme, récupère URL et clé, et code son appli autour.
- Il place ses tables dans des **fichiers de migration** dans son dépôt.
- Au déploiement chez un client, la plateforme crée un backend neuf, applique les migrations, puis injecte la nouvelle URL et la nouvelle clé dans l'appli.
- Briques envisagées : PostgreSQL (licence PostgreSQL) et PostgREST (MIT) pour l'API auto, connexion par SMS codée par l'équipe. Bases hébergées sur le service de bases de données Datacloud, pour que l'argent reste chez Systalink.
- On ne reconstruit pas Supabase en entier. On prend juste ce qu'il faut pour montrer l'idée.

### 5.4 L'espace d'échange central entre devs (cœur du thème)
- Plus qu'un simple chat : **tout ce qui se partage peut tourner en un clic**.
- Un dev qui poste un problème joint son appli. La plateforme lance une **copie de test** de son appli.
- Les autres devs ouvrent cette copie, reproduisent le bug, proposent un correctif et **déploient leur version corrigée** pour prouver que ça marche.
- L'auteur voit la version réparée tourner avant d'accepter le correctif.
- Exemple : « mon paiement MoMo plante ». Un dev de Lomé ouvre la copie, corrige, et l'auteur voit le correctif fonctionner.
- Argument : une IA ne voit pas l'environnement réel du dev, la plateforme si.

### 5.5 L'échange autour de chaque appli
- Chaque appli a son espace de discussion.
- Un client peut écrire directement à l'auteur pour une version adaptée (ex. une clinique veut le template pharmacie avec gestion des rendez-vous).
- **Pas d'enchères, pas de mise en concurrence** : l'auteur décroche la mission directement.

### 5.6 Paiement
- Le client paie sur Datacloud, au même endroit que l'hébergement, par Mobile Money ou carte. Un seul paiement couvre hébergement + prix de l'appli.
- La plateforme reverse la part de l'appli au dev. **Aucune commission** sur ce montant.
- Si l'abonnement n'est pas payé, l'appli du client est **suspendue**, puis réactivée dès le paiement. Le dev n'a jamais à relancer un client.

---

## 6. Business model

- **Devs** : revenus via le prix de leurs applis et les missions sur mesure.
- **Systalink** : revenus via l'hébergement de chaque copie déployée (serveurs, bases, stockage). Aucune commission prélevée sur les devs.
- Exemple : 20 pharmacies déploient un template à 15 000 F/mois, le dev touche 300 000 F/mois, Systalink facture 20 hébergements.

---

## 7. Positionnement face au catalogue Systalink

- Datacloud propose des briques séparées : hébergement web, serveurs cloud, bases de données, stockage, serverless, e-mail, domaines, et un créateur de site par IA.
- Pas de store d'applis prêtes à déployer, ni d'espace d'échange entre devs.
- Le créateur de site IA propose déjà Supabase comme backend (« bientôt disponible »), mais seulement pour les sites générés par IA. Les devs qui codent à la main n'y ont pas accès.
- Argument pitch : avec Souk.dev, le client Systalink a trois chemins : générer son site par IA, choisir une appli toute prête, ou coder la sienne. Souk.dev comble le vide entre « je génère un site simple » et « je code tout moi-même ».

---

## 8. Idées écartées (ne pas reproposer)

- Échange de temps entre devs contre des crédits (l'entraide gratuite recule à cause de l'IA).
- Marketplace de missions avec plusieurs équipes en concurrence (course au moins cher).
- Attribution automatique de missions par réputation (les nouveaux ne trouvent jamais de clients).
- Hub d'intégrations locales (MoMo, Wave…) avec passerelle de paiement à commission (double ponction pour les devs).
- Plateforme de partage de données africaines.
- Plateforme d'organisation de hackathons.
- Supabase cloud créé automatiquement pour chaque client (limites de l'offre gratuite, coût payé à Supabase, marge Systalink qui fond).
- Reprendre Supabase entier en le renommant (risque de plagiat, article 11).
- Toute commission prélevée sur les devs.

---

## 9. Périmètre MVP pour le concours

À livrer :
1. Publier une appli (lien GitHub + docker-compose + fichier de description + démo + prix).
2. La voir dans le store.
3. La déployer en un clic sur Datacloud, copie isolée par client.
4. L'espace d'échange central avec partage d'une copie de test et proposition de correctif.

Version simple si le temps manque : backend intégré limité à base + API auto + connexion par SMS.

Hors MVP (à présenter comme la suite) : avis et notes, catégories avancées, mises à jour poussées à tous les clients, version complète du backend intégré, paiement réel de bout en bout.

---

## 10. Points ouverts

- **Stack technique et architecture** : à décider par Oktav.
- **Hébergement** : réglé le 9 octobre avec Systalink. Un VPS Datacloud partagé pour le concours, le serverless Datacloud ensuite (détail dans [`DECISIONS.md`](DECISIONS.md#hébergement)).
- Répartition des tâches entre les membres.
- Confirmation officielle de la date limite (25 ou 31 octobre).
- Recrutement des trois membres restants.
- Nom définitif du fichier de description (`soukdev.json` provisoire).
