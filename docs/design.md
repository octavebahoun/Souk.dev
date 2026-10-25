# Système de design — Souk.dev

Référence de l'interface : principes, tokens, composants et règles d'écran. Les tokens vivent dans
`frontend/src/index.css` ; les composants dans `frontend/src/shared/ui/`. Un écran n'utilise que
ces tokens et ces composants : aucune couleur, aucune taille, aucune ombre codée en dur.

Identité proche du design system de Systalink (bleu `#1265DD`, rayons de 8 px, police Outfit), sans
reprendre leur logo, leur nom ni leurs images. Niveau visé : la densité de Linear, la clarté des
paiements et des tableaux de Stripe, le suivi de déploiement de Vercel et de Railway.

## 1. Principes

1. **La confiance d'abord.** Un client non technicien (une pharmacie, une association) ne paie que
   s'il comprend. Montants exacts en FCFA, frais annoncés avant de payer, état visible à chaque
   étape (paiement, déploiement), aucun jargon technique côté client.
2. **Une action principale par écran**, visuellement dominante (bouton primaire). Les actions
   secondaires sont en retrait (bouton secondaire ou fantôme) ; les actions destructrices sont
   éloignées, rouges et confirmées.
3. **Les devs d'abord.** L'Échange et le Store sont au premier plan ; le client est un débouché.
4. **Densité maîtrisée.** Listes compactes sur bureau, cibles de 44 × 44 px sur mobile.
5. **Africain, sans folklore.** Visages et contextes d'Afrique de l'Ouest et du Centre dans les
   visuels, motifs géométriques discrets ; jamais de cliché décoratif.

## 2. Couleurs

Tokens sémantiques, redéfinis pour le thème sombre (même palette, base bleu nuit). Tous les couples
texte/fond ci-dessous ont été calculés : 4,5:1 au moins pour le texte, 3:1 pour les bordures des champs.

| Token | Rôle | Clair | Sombre |
|---|---|---|---|
| `fond` | Fond de page | `#F7FAFF` | `#0B0D1E` |
| `surface` | Cartes, panneaux, champs | `#FFFFFF` | `#12152B` |
| `surface-douce` | Survol, lignes alternées, zones calmes | `#F2F6FC` | `#171B35` |
| `surface-accent` | Élément sélectionné, puces, sections bleutées | `#ECF3FF` | `#1B2550` |
| `nuit` | En-têtes sombres, hero, dégradés | `#160C51` | `#0F0A3A` |
| `bordure` | Séparateurs, contours de cartes | `#E3E9F3` | `#252A48` |
| `bordure-champ` | Contour des champs (3:1) | `#8290A6` | `#5E6A91` |
| `titre` | Titres | `#1F1F1F` | `#F4F6FB` |
| `texte` | Texte courant | `#364153` | `#C5CCDB` |
| `texte-doux` | Texte secondaire, méta | `#5F6B7E` | `#98A2B8` |
| `texte-indice` | Texte indicatif des champs | `#66728A` | `#8790A6` |
| `primaire` | Action principale, élément actif | `#1265DD` | `#1265DD` |
| `primaire-survol` / `primaire-actif` | États du bouton primaire | `#0F56BE` / `#0C489F` | `#2C7AEA` / `#4A8CF0` |
| `primaire-doux` / `sur-primaire-doux` | Fond et texte d'un bouton secondaire bleu | `#ECF3FF` / `#1265DD` | `#16264D` / `#8DB8FF` |
| `lien` | Liens, accents, focus | `#0C60F7` | `#6EA8FF` |
| `vert-marque` | Accent positif décoratif (points, illustrations) | `#0CCF87` | `#0CCF87` |
| `succes` / `fond-succes` / `bordure-succes` | Réussite, en ligne, payé | `#067647` / `#ECFDF3` / `#ABEFC6` | `#47CD89` / `#0E2A1E` / `#1C5A3D` |
| `alerte` / `fond-alerte` / `bordure-alerte` | Attention, en retard, problèmes détectés | `#B54708` / `#FFFAEB` / `#FEDF89` | `#FDB022` / `#2B1F0A` / `#5C3F0F` |
| `danger` / `fond-danger` / `bordure-danger` | Échec, suppression, suspendu | `#B42318` / `#FEF3F2` / `#FECDCA` | `#F97066` / `#2D1214` / `#6B1E1E` |
| `danger-solide` | Fond d'un bouton destructeur (texte blanc) | `#D92D20` | `#D92D20` |
| `info` / `fond-info` / `bordure-info` | Information neutre | `#175CD3` / `#EFF8FF` / `#B2DDFF` | `#84CAFF` / `#0B2342` / `#1B4C80` |
| `ia` / `fond-ia` / `bordure-ia` | Souk AI (suggestion, jamais une solution) | `#5B3FD9` / `#F4F1FF` / `#DDD3FF` | `#B8A6FF` / `#1E1640` / `#3A2D7A` |

Règles : le vert de marque `#0CCF87` et le vert succès `#12B76A` ne portent jamais de texte (contraste
insuffisant) ; on écrit en `succes`. Le rouge n'apparaît que pour une erreur ou une action destructrice.

## 3. Typographie

- **Outfit** (variable, OFL) pour toute l'interface ; **IBM Plex Mono** (OFL) pour le code, les
  identifiants de copie et les références de paiement.
- Chiffres tabulaires (`tabular-nums`) dans les montants, tableaux et compteurs.

| Style | Taille / interligne | Graisse | Approche | Usage |
|---|---|---|---|---|
| `affiche` | 56/60 (36/42 mobile) | 600 | -0,03 em | Hero de l'accueil |
| `titre-1` | 32/40 (26/32 mobile) | 600 | -0,02 em | Titre de page |
| `titre-2` | 24/32 | 600 | -0,015 em | Section |
| `titre-3` | 18/26 | 600 | -0,01 em | Carte, panneau |
| `corps-l` | 17/28 | 400 | 0 | Paragraphes de l'accueil |
| `corps` | 15/24 | 400 | 0 | Texte courant |
| `petit` | 13/20 | 400 | 0 | Méta, aides de champ |
| `etiquette` | 12/16 | 500 | 0,04 em, capitales | En-têtes de colonnes, sur-titres |

## 4. Espacement, grille, rayons, ombres

- Échelle de **4 px** (utilitaires Tailwind : `1` = 4 px). Gouttières : 16 px mobile, 24 px tablette,
  32 px bureau. Largeur utile : 1200 px (espace connecté), 1280 px (accueil).
- Points de rupture : mobile < 768 px, **tablette** 768–1199 px, **bureau** ≥ 1200 px (`tablette:`, `bureau:`).
- Rayons : `petit` 6 px (puces, petits champs), `moyen` **8 px** (boutons, cartes, champs),
  `grand` 12 px (modales, panneaux), `xl` 16 px (cartes d'accueil), `pastille` (999 px).
- Ombres : `ombre-1` (repos d'une carte), `ombre-2` (survol), `ombre-3` (menus, popovers),
  `ombre-4` (modales). En sombre, les ombres s'effacent au profit des bordures.

## 5. Mouvement

Le mouvement sert l'information : étapes d'un déploiement, arrivée d'un message, résultat d'un
paiement. Durées 150 ms (survol, petits états), 200 ms (apparitions), 300 ms (panneaux, modales),
courbe `cubic-bezier(0.2, 0.7, 0.2, 1)`, seulement sur `transform` et `opacity`. Avec
`prefers-reduced-motion`, plus de déplacement : un simple fondu, ou rien.

## 6. Composants (`frontend/src/shared/ui/`)

| Composant | Rôle et règles |
|---|---|
| `Bouton`, `LienBouton` | Variantes `primaire`, `secondaire`, `fantome`, `danger` ; tailles `sm` (32 px), `md` (40 px, 44 px sur mobile), `lg` (48 px). État `chargement` (indicateur + `aria-busy`), désactivé tant qu'un formulaire est incomplet. Une icône à gauche au plus. |
| `Champ` | Étiquette toujours visible, aide sous le champ, erreur reliée par `aria-describedby` et `aria-invalid`, texte qui dit quoi faire. Variantes texte, zone de texte, liste, prix (FCFA). |
| `CaseACocher`, `Interrupteur`, `ChoixCartes` | Choix binaires et choix parmi peu d'options (formule d'hébergement, réseau Mobile Money). |
| `Carte` | Surface bordée ; en-tête, contenu, pied. `CarteLien` : toute la carte est cliquable, avec un seul lien. |
| `Badge`, `PastilleEtat` | Badge : catégorie, techno, statut neutre. Pastille d'état : point coloré + libellé, annoncée aux lecteurs d'écran. Un état = une couleur, partout (voir § 8). |
| `Onglets` | Rôles ARIA `tablist`/`tab`/`tabpanel`, flèches du clavier. |
| `Modale` | Titre, piège de focus, Échap, retour du focus au déclencheur. Confirmation des actions destructrices. |
| `Toasts` | Résultat d'une action, annoncé (`aria-live`) ; jamais pour une erreur bloquante (elle reste dans l'écran). |
| `Squelette` | Chargement à la forme du contenu attendu, jamais un écran blanc. |
| `EtatVide`, `EtatErreur`, `AccesRefuse`, `BandeauHorsLigne`, `AvisDonneesPartielles` | Les états obligatoires de chaque écran (§ 7). |
| `EnTetePage`, `FilAriane` | Titre, description, action principale à droite (dessous sur mobile). |
| `Tableau` | En-têtes `etiquette`, chiffres alignés à droite ; sur mobile, chaque ligne devient une carte. |
| `Etapes` | Suivi d'un déploiement ou d'un paiement : étape courante annoncée (`aria-current="step"`). |
| `Montant` | FCFA formatés (`15 000 F`), chiffres tabulaires. |
| `Avatar`, `Logo`, `Icone` | Avatar : photo ou initiales sur une teinte de la palette. Logo Souk.dev en SVG. Icônes Lucide, trait 1,75. |
| `MenuDeroulant`, `Pagination`, `Interrupteur de thème` | Menu au clavier (flèches, Échap), pagination numérotée, thème clair / sombre / système. |

## 7. États obligatoires de chaque écran

1. **Chargement** : squelettes à la forme du contenu.
2. **Vide** : illustration, une phrase qui explique, l'action pour en sortir.
3. **Erreur** : ce qui s'est passé, quoi faire, bouton « Réessayer ».
4. **Succès** : confirmation visible (toast ou écran de résultat).
5. **Accès refusé** : pourquoi, et comment se connecter ou revenir.
6. **Hors ligne** : bandeau persistant tant que la connexion manque.
7. **Données partielles** : ce qui manque et pourquoi (ex. dépôts GitHub indisponibles).

## 8. Couleurs d'état (une seule table pour tout le produit)

| État | Teinte | Exemples |
|---|---|---|
| En cours | `info` (point animé) | `en_file`, `construction`, `demarrage`, paiement `PENDING`, analyse en cours |
| Réussi | `succes` | `en_ligne`, payé, Security Checked, mission terminée |
| Attention | `alerte` | facture à payer, problèmes détectés, en retard |
| Échec / arrêt forcé | `danger` | `echec`, paiement refusé, suspendu |
| Neutre | `texte-doux` | `arrete`, brouillon, annulé |
| Souk AI | `ia` | analyse IA |

## 9. Règles d'écran

- Hiérarchie : titre de page, puis contenu, puis actions. Lecture en F pour les listes, en Z pour l'accueil.
- Ce qui compte est au-dessus de la ligne de flottaison (prix et bouton « Déployer » sur une fiche).
- Proximité et alignement : ce qui va ensemble est groupé, sur la grille de 4 px.
- Loi de Hick : peu de choix visibles ; filtres avancés repliés.
- Conventions (loi de Jakob) : navigation en haut, recherche en haut, compte en haut à droite ;
  barre d'onglets en bas sur mobile pour les destinations principales.
- Même composant, même emplacement, même libellé d'une page à l'autre.
- Accessibilité WCAG 2.2 AA : clavier complet, focus visible (anneau `lien` de 3 px), étiquettes,
  erreurs reliées, contrastes, annonces des changements d'état.
- Textes hors du code (`frontend/src/i18n/`), pour permettre l'anglais ensuite. Vouvoiement.

## 10. Visuels

- Illustrations des états vides, de l'accueil et des étapes : Nano Banana Pro (voir `scripts/visuels/`,
  `AI_USAGE.md`), style plat, palette de la marque, sans texte. Présentées dans un cadre `surface-accent`
  aux coins de 12 px, qui reste lisible dans les deux thèmes.
- Logos de marques (réseaux Mobile Money, technos) : seulement pour désigner la marque, jamais en
  décoration ni pour suggérer un partenariat (`LICENSES.md`).
