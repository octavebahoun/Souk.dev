import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight, BadgeCheck, CircleCheck, HandCoins, MessagesSquare, Server, ShieldCheck, Smartphone } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { listerApplis } from '../catalogue/api'
import { CarteAppli } from '../catalogue/CarteAppli'
import { listerDiscussions } from '../echange/api'
import { formules } from '../paiements/formules'
import { Badge, Carte, Chargement, EtatErreur, EtatVide, LienBouton, SqueletteCartes, SqueletteLignes, cx, formaterFcfa } from '../../ui'
import hero from '../../assets/accueil/hero.webp'
import etapePublier from '../../assets/accueil/etape-publier.webp'
import etapeDeployer from '../../assets/accueil/etape-deployer.webp'
import etapeEncaisser from '../../assets/accueil/etape-encaisser.webp'

const conteneur = 'mx-auto w-full max-w-site px-4 tablette:px-6 bureau:px-8'

const etapes = [
  {
    image: etapePublier,
    titre: 'Publiez votre appli',
    texte: 'Liez un dépôt GitHub public et fixez votre prix. Souk.dev vérifie les secrets et les dépendances avant la mise en ligne.',
  },
  {
    image: etapeDeployer,
    titre: 'Un client la déploie en un clic',
    texte: 'Une pharmacie, une association : chacune a sa copie, isolée, avec ses propres données. Sans serveur à gérer.',
  },
  {
    image: etapeEncaisser,
    titre: 'Vous encaissez, sans commission',
    texte: 'Le client paie par Mobile Money, en FCFA. Le prix de l’appli vous revient en entier.',
  },
]

/**
 * Accueil (lecture en Z) : la promesse et les deux portes d'entrée sur le bandeau bleu nuit, puis
 * le parcours en trois étapes, les applis du Store, l'Échange, qui paie quoi, et l'appel final.
 */
export function Accueil() {
  useTitrePage(null)
  const applis = useQuery({ queryKey: ['applis', 'accueil'], queryFn: () => listerApplis({ tri: 'recent' }) })
  const discussions = useQuery({ queryKey: ['discussions', 'accueil'], queryFn: () => listerDiscussions({ page: 1 }) })
  const vitrine = applis.data?.data.slice(0, 4) ?? []
  const fil = discussions.data?.data.slice(0, 4) ?? []

  return (
    <div className="flex flex-col">
      <section aria-labelledby="titre-accueil" className="relative isolate overflow-hidden bg-nuit text-sur-couleur">
        <img
          src={hero}
          alt=""
          width={1376}
          height={768}
          fetchPriority="high"
          className="absolute inset-0 -z-10 size-full object-cover object-right opacity-50 tablette:opacity-100"
        />
        <div aria-hidden="true" className="absolute inset-0 -z-10 bg-linear-to-r from-nuit via-nuit/85 to-transparent" />
        <div className={cx(conteneur, 'flex flex-col gap-6 py-16 tablette:py-24')}>
          <span className="inline-flex w-fit items-center gap-2 rounded-pastille border border-sur-couleur/20 bg-sur-couleur/10 px-3 py-1 text-etiquette uppercase">
            <span aria-hidden="true" className="size-2 rounded-pastille bg-vert-marque" />
            Pour les devs, par les devs
          </span>
          <h1 id="titre-accueil" className="m-0 max-w-[14ch] text-titre-1 text-sur-couleur tablette:text-affiche">
            Le marché des devs africains.
          </h1>
          <p className="m-0 max-w-prose text-corps-l text-sur-couleur/85">
            Postez un bug, un autre dev le corrige. Publiez votre appli, une entreprise la déploie en un clic. Ici, l’entraide devient un revenu.
          </p>
          <div className="flex flex-col gap-3 tablette:flex-row">
            <LienBouton to="/connexion" taille="lg" iconeFin={<ArrowRight aria-hidden="true" className="size-4" />}>
              Je suis dev
            </LienBouton>
            <LienBouton to="/store" taille="lg" variante="contour">
              Je cherche une appli
            </LienBouton>
          </div>
          <ul className="m-0 flex list-none flex-wrap gap-x-6 gap-y-2 p-0 text-petit text-sur-couleur/80">
            <li className="flex items-center gap-2"><CircleCheck aria-hidden="true" className="size-4 text-vert-marque" />0 % de commission</li>
            <li className="flex items-center gap-2"><Smartphone aria-hidden="true" className="size-4 text-vert-marque" />Mobile Money, en FCFA</li>
            <li className="flex items-center gap-2"><ShieldCheck aria-hidden="true" className="size-4 text-vert-marque" />Chaque appli analysée avant publication</li>
          </ul>
        </div>
      </section>

      <section aria-labelledby="titre-etapes" className={cx(conteneur, 'flex flex-col gap-8 py-16')}>
        <div className="flex max-w-prose flex-col gap-2">
          <p className="m-0 text-etiquette text-lien uppercase">Comment ça marche</p>
          <h2 id="titre-etapes" className="m-0 text-titre-1 text-titre">D’un dépôt GitHub à un client qui paie</h2>
        </div>
        <ol className="m-0 grid list-none gap-5 p-0 tablette:grid-cols-3">
          {etapes.map((etape, numero) => (
            <li key={etape.titre} className="flex">
              <Carte marge="aucune" className="flex w-full flex-col overflow-hidden">
                <img src={etape.image} alt="" width={800} height={597} loading="lazy" className="aspect-video w-full border-b border-bordure object-cover tablette:aspect-[4/3]" />
                <div className="flex flex-col gap-2 p-5">
                  <h3 className="m-0 flex items-center gap-3 text-titre-3 text-titre">
                    <span aria-hidden="true" className="chiffres grid size-7 shrink-0 place-items-center rounded-pastille bg-primaire text-petit text-sur-primaire">{numero + 1}</span>
                    {etape.titre}
                  </h3>
                  <p className="m-0 text-corps text-texte">{etape.texte}</p>
                </div>
              </Carte>
            </li>
          ))}
        </ol>
      </section>

      <section aria-labelledby="titre-vitrine" className="border-y border-bordure bg-surface-douce">
        <div className={cx(conteneur, 'flex flex-col gap-6 py-16')}>
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="flex max-w-prose flex-col gap-2">
              <p className="m-0 text-etiquette text-lien uppercase">Dans le Store</p>
              <h2 id="titre-vitrine" className="m-0 text-titre-1 text-titre">Des applis faites ici, prêtes à servir</h2>
            </div>
            <LienBouton to="/store" variante="fantome" iconeFin={<ArrowRight aria-hidden="true" className="size-4" />}>
              Tout le Store
            </LienBouton>
          </div>
          {applis.isPending ? <SqueletteCartes nombre={4} /> : null}
          {applis.isError ? <EtatErreur niveau={3} description="Le Store n’a pas pu charger les applis." onReessayer={() => void applis.refetch()} /> : null}
          {applis.isSuccess && vitrine.length === 0 ? (
            <EtatVide
              illustration="vide-store"
              niveau={3}
              titre="Aucune appli publiée"
              description="Les fiches apparaîtront ici après une publication réelle."
              action={<LienBouton to="/publier">Publier une appli</LienBouton>}
            />
          ) : null}
          {vitrine.length > 0 ? (
            <ul className="m-0 grid list-none gap-5 p-0 tablette:grid-cols-2 bureau:grid-cols-4">
              {vitrine.map((appli) => (
                <li key={appli.id} className="flex">
                  <CarteAppli appli={appli} niveau={3} />
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      </section>

      <section aria-labelledby="titre-echange" className={cx(conteneur, 'grid gap-8 py-16 bureau:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] bureau:items-start')}>
        <div className="flex flex-col gap-4">
          <p className="m-0 text-etiquette text-lien uppercase">L’Échange</p>
          <h2 id="titre-echange" className="m-0 text-titre-1 text-titre">Un bug ? Un autre dev le corrige</h2>
          <p className="m-0 text-corps-l text-texte">
            Posez le problème avec votre dépôt. Un dev propose sa branche, vous l’essayez sur une copie de test, puis vous acceptez. C’est gratuit, et ça construit votre réputation.
          </p>
          <LienBouton to="/echange" variante="secondaire" className="self-start" icone={<MessagesSquare aria-hidden="true" className="size-4" />}>
            Ouvrir l’Échange
          </LienBouton>
        </div>
        <Carte marge="aucune" className="overflow-hidden">
          {discussions.isPending ? (
            <Chargement className="p-5">
              <SqueletteLignes lignes={4} />
            </Chargement>
          ) : null}
          {discussions.isError ? <EtatErreur niveau={3} className="p-5" description="L’Échange n’a pas pu charger les discussions." onReessayer={() => void discussions.refetch()} /> : null}
          {discussions.isSuccess && fil.length === 0 ? <p className="m-0 p-5 text-corps text-texte-doux">Aucune discussion publique publiée pour le moment.</p> : null}
          {fil.length > 0 ? (
            <ul className="m-0 flex list-none flex-col p-0">
              {fil.map((discussion) => (
                <li key={discussion.id} className="relative flex flex-col gap-1.5 border-t border-bordure px-5 py-4 first:border-t-0 hover:bg-surface-douce">
                  <Link to={`/echange/${discussion.id}`} className="font-medium text-titre no-underline after:absolute after:inset-0 after:content-['']">
                    {discussion.titre}
                  </Link>
                  <span className="flex flex-wrap items-center gap-2.5 text-petit text-texte-doux">
                    <Badge ton={discussion.resolue ? 'succes' : 'marque'}>{discussion.resolue ? 'Résolue' : 'Ouverte'}</Badge>
                    <span>{discussion.auteur.nom || discussion.auteur.username}</span>
                    <span>{discussion.nb_messages} message{discussion.nb_messages > 1 ? 's' : ''}</span>
                  </span>
                </li>
              ))}
            </ul>
          ) : null}
        </Carte>
      </section>

      <section aria-labelledby="titre-prix" className="border-t border-bordure bg-surface">
        <div className={cx(conteneur, 'flex flex-col gap-8 py-16')}>
          <div className="flex max-w-prose flex-col gap-2">
            <p className="m-0 text-etiquette text-lien uppercase">Transparent</p>
            <h2 id="titre-prix" className="m-0 text-titre-1 text-titre">Qui paie quoi ?</h2>
          </div>
          <ul className="m-0 grid list-none gap-5 p-0 tablette:grid-cols-3">
            <Regle icone={<BadgeCheck aria-hidden="true" className="size-5" />} ton="bg-fond-succes text-succes" titre="Gratuit">
              Poster un problème, aider, corriger, publier son appli : rien de tout ça ne lance de serveur.
            </Regle>
            <Regle icone={<HandCoins aria-hidden="true" className="size-5" />} ton="bg-fond-alerte text-alerte" titre="Le prix de l’appli">
              Fixé par le dev, versé au dev. Souk.dev ne prend aucune commission.
            </Regle>
            <Regle icone={<Server aria-hidden="true" className="size-5" />} ton="bg-primaire-doux text-sur-primaire-doux" titre="L’hébergement">
              Seulement quand une copie tourne : dès {formaterFcfa(formules.petite.prix)} par mois, selon la taille choisie.
            </Regle>
          </ul>
        </div>
      </section>

      <section aria-labelledby="titre-final" className={cx(conteneur, 'pb-16')}>
        <div className="flex flex-col gap-5 rounded-grand bg-nuit p-8 text-sur-couleur tablette:flex-row tablette:items-center tablette:justify-between tablette:p-12">
          <div className="flex max-w-prose flex-col gap-2">
            <h2 id="titre-final" className="m-0 text-titre-1 text-sur-couleur">Venez au souk.</h2>
            <p className="m-0 text-corps-l text-sur-couleur/85">Posez votre premier problème, ou trouvez l’appli qui fera tourner votre activité dès demain.</p>
          </div>
          <div className="flex shrink-0 flex-col gap-3 tablette:flex-row">
            <LienBouton to="/connexion" taille="lg">
              Je suis dev
            </LienBouton>
            <LienBouton to="/store" taille="lg" variante="contour">
              Je cherche une appli
            </LienBouton>
          </div>
        </div>
      </section>
    </div>
  )
}

function Regle({ icone, ton, titre, children }: { icone: ReactNode; ton: string; titre: string; children: ReactNode }) {
  return (
    <li className="flex flex-col gap-3 rounded-moyen border border-bordure bg-fond p-5">
      <span className={cx('grid size-10 place-items-center rounded-moyen', ton)}>{icone}</span>
      <h3 className="m-0 text-titre-3 text-titre">{titre}</h3>
      <p className="m-0 text-corps text-texte">{children}</p>
    </li>
  )
}
