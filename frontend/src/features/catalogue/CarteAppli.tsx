import { Link } from 'react-router'
import { fcfa, libelleCategorie, type Appli } from './api'
import { BadgeSecurite } from './BadgeSecurite'
import { Badge, Etoiles, formaterNote } from '../../ui'

/** Une appli : capture, nom (seul lien, qui couvre la carte), auteur, note, description, prix. */
export function CarteAppli({ appli, niveau = 2 }: { appli: Appli; niveau?: 2 | 3 }) {
  const Titre = niveau === 2 ? 'h2' : 'h3'
  const prix = appli.prix === 0 ? 'Gratuit' : fcfa(appli.prix)
  return (
    <article className="group relative flex w-full flex-col overflow-hidden rounded-moyen border border-bordure bg-surface shadow-1 transition-[box-shadow,border-color,transform] duration-200 ease-souk hover:-translate-y-0.5 hover:border-bordure-champ hover:shadow-3 has-[a:focus-visible]:outline-3 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-lien">
      {appli.captures[0] ? (
        <img
          src={appli.captures[0].url}
          alt=""
          loading="lazy"
          className="aspect-video w-full border-b border-bordure bg-surface-douce object-cover object-top"
        />
      ) : (
        <div aria-hidden="true" className="grid aspect-video place-items-center border-b border-bordure bg-surface-accent text-titre-2 text-sur-primaire-doux">
          {appli.nom.slice(0, 2).toUpperCase()}
        </div>
      )}
      <div className="flex flex-1 flex-col gap-3 p-4">
        <div className="flex flex-wrap gap-1.5">
          <Badge ton="marque">{libelleCategorie(appli.categorie)}</Badge>
          <BadgeSecurite statut={appli.securite.statut} />
        </div>
        <div className="flex flex-col gap-0.5">
          <Titre className="m-0 text-titre-3">
            <Link to={`/store/${appli.id}`} className="text-titre no-underline after:absolute after:inset-0 after:content-[''] focus-visible:outline-none">
              {appli.nom}
            </Link>
          </Titre>
          <p className="m-0 text-petit text-texte-doux">par {appli.auteur.nom || appli.auteur.username || 'un dev'}</p>
        </div>
        <p className="m-0 line-clamp-3 flex-1 text-corps text-texte">{appli.description}</p>
        <div className="flex items-center justify-between gap-2 border-t border-bordure pt-3">
          <p className="m-0 text-corps font-semibold text-titre">
            <span className="chiffres">{prix}</span>
            {appli.prix > 0 && appli.type_prix === 'mensuel' ? <span className="font-normal text-texte-doux"> / mois</span> : null}
          </p>
          {appli.note_moyenne !== null ? (
            <p className="m-0 flex items-center gap-1.5 text-petit text-texte-doux">
              <Etoiles note={appli.note_moyenne} taille="sm" />
              <span aria-hidden="true" className="font-medium text-titre">{formaterNote(appli.note_moyenne)}</span>
              <span>({appli.nb_avis})</span>
            </p>
          ) : null}
        </div>
      </div>
    </article>
  )
}
