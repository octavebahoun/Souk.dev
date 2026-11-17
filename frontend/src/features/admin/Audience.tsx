import { useState } from 'react'
import { Link } from 'react-router'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { ChartColumn, FileText, Globe, Link2, MonitorSmartphone } from 'lucide-react'
import type { ReactNode } from 'react'
import { useTitrePage } from '../../app/titre'
import { Carte, ChoixPastilles, EnTetePage, EtatErreur, Squelette, cx } from '../../ui'
import { lireAudience, type LigneAudience } from './api'

type Periode = 7 | 30 | 90

const jourCourt = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' })
const jourLong = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const nombre = new Intl.NumberFormat('fr-FR')
const regions = typeof Intl.DisplayNames === 'function' ? new Intl.DisplayNames(['fr'], { type: 'region' }) : null
const appareils: Record<string, string> = { mobile: 'Téléphone', tablette: 'Tablette', ordinateur: 'Ordinateur', inconnu: 'Inconnu' }

/** « 1 visiteur », « 3 visiteurs » : le nombre et son mot, accordé. */
function compte(valeur: number, singulier: string, pluriel: string): ReactNode {
  return (
    <>
      <span className="chiffres font-medium text-titre">{nombre.format(valeur)}</span> {valeur > 1 ? pluriel : singulier}
    </>
  )
}

function date(jour: string): Date {
  return new Date(`${jour}T12:00:00`)
}

/**
 * Audience (équipe) : combien de visiteurs, d'où ils viennent, ce qu'ils regardent. Sans cookie ni
 * adresse IP : un visiteur compte une fois par jour, on ne suit personne d'un jour à l'autre.
 */
export function Audience() {
  useTitrePage('Audience', { sansIndex: true })
  const [jours, setJours] = useState<Periode>(30)
  const audience = useQuery({ queryKey: ['admin', 'audience', jours], queryFn: () => lireAudience(jours), placeholderData: keepPreviousData, refetchInterval: 60_000 })
  const donnees = audience.data

  return (
    <div className="flex flex-col gap-6">
      <EnTetePage
        titre="Audience"
        description="Qui visite Souk.dev, d’où, et quelles pages. Sans cookie ni adresse IP gardée : un visiteur compte une fois par jour, personne n’est suivi."
      />

      <ChoixPastilles
        libelle="Période"
        valeur={String(jours)}
        onChange={(valeur) => setJours(Number(valeur) as Periode)}
        options={[
          { valeur: '7', libelle: '7 jours' },
          { valeur: '30', libelle: '30 jours' },
          { valeur: '90', libelle: '90 jours' },
        ]}
      />

      {audience.isError ? <EtatErreur description="L’audience n’a pas pu être chargée." onReessayer={() => void audience.refetch()} /> : null}
      {audience.isPending ? <Squelette className="h-80 rounded-moyen" /> : null}

      {donnees ? (
        <>
          <dl className="m-0 grid gap-3 tablette:grid-cols-2 bureau:grid-cols-4">
            <Chiffre titre="Visiteurs aujourd’hui" valeur={donnees.aujourdhui.visiteurs} mis />
            <Chiffre titre="Pages vues aujourd’hui" valeur={donnees.aujourdhui.pages} />
            <Chiffre titre={`Visiteurs sur ${jours} jours`} valeur={donnees.periode.visiteurs} detail="additionnés jour par jour" />
            <Chiffre titre={`Pages vues sur ${jours} jours`} valeur={donnees.periode.pages} />
          </dl>

          <Carte as="section" aria-labelledby="titre-par-jour" className="flex flex-col gap-4">
            <h2 id="titre-par-jour" className="m-0 flex items-center gap-2 text-titre-3 text-titre">
              <ChartColumn aria-hidden="true" className="size-5 text-texte-doux" />
              Visiteurs par jour
            </h2>
            <Histogramme serie={donnees.par_jour} />
          </Carte>

          <div className="grid gap-4 bureau:grid-cols-2">
            <Classement titre="Pages les plus vues" icone={<FileText aria-hidden="true" className="size-5 text-texte-doux" />} lignes={donnees.pages} libelle={(nom) => <Link to={nom}>{nom}</Link>} vide="Aucune page vue sur la période." />
            <Classement
              titre="D’où ils viennent"
              icone={<Link2 aria-hidden="true" className="size-5 text-texte-doux" />}
              lignes={donnees.sources}
              libelle={(nom) => nom}
              vide="Pas encore de site d’origine. Les liens ouverts depuis WhatsApp ou tapés à la main arrivent sans origine."
              apres={
                donnees.campagnes.length > 0 ? (
                  <div className="flex flex-col gap-2 border-t border-bordure pt-3">
                    <p className="m-0 text-petit font-medium text-titre">Liens suivis (utm_source)</p>
                    <Barres lignes={donnees.campagnes} libelle={(nom) => nom} />
                  </div>
                ) : (
                  <p className="m-0 border-t border-bordure pt-3 text-petit text-texte-doux">
                    Astuce : partagez <code>soukdev.shop/?utm_source=whatsapp</code> pour savoir combien de visiteurs viennent de WhatsApp.
                  </p>
                )
              }
            />
            <Classement
              titre="Pays (estimé)"
              icone={<Globe aria-hidden="true" className="size-5 text-texte-doux" />}
              lignes={donnees.pays}
              libelle={(code) => regions?.of(code) ?? code}
              vide="Aucun pays reconnu sur la période."
              apres={<p className="m-0 text-petit text-texte-doux">D’après le fuseau horaire du navigateur : le Bénin peut apparaître comme Nigeria (même fuseau).</p>}
            />
            <Classement titre="Appareils" icone={<MonitorSmartphone aria-hidden="true" className="size-5 text-texte-doux" />} lignes={donnees.appareils} libelle={(nom) => appareils[nom] ?? nom} vide="Aucun appareil sur la période." />
          </div>
        </>
      ) : null}
    </div>
  )
}

function Chiffre({ titre, valeur, detail, mis = false }: { titre: string; valeur: number; detail?: string; mis?: boolean }) {
  return (
    <Carte as="div" className={cx('flex flex-col gap-1', mis && 'border-primaire bg-surface-accent')}>
      <dt className={cx('text-petit', mis ? 'text-sur-primaire-doux' : 'text-texte-doux')}>{titre}</dt>
      <dd className="m-0 chiffres text-titre-1 text-titre">{nombre.format(valeur)}</dd>
      {detail ? <dd className="m-0 text-petit text-texte-doux">{detail}</dd> : null}
    </Carte>
  )
}

/** Une barre par jour, une seule série (visiteurs) ; survol ou focus : la date, les visiteurs, les pages. */
function Histogramme({ serie }: { serie: { jour: string; visiteurs: number; pages: number }[] }) {
  const [actif, setActif] = useState<number | null>(null)
  const max = Math.max(1, ...serie.map((point) => point.visiteurs))
  const reperes = [0, Math.floor((serie.length - 1) / 2), serie.length - 1]
  const point = actif === null ? null : serie[actif]

  return (
    <div className="flex flex-col gap-3">
      <div className="relative">
        <p className="m-0 mb-1 text-petit text-texte-doux">
          max. <span className="chiffres">{nombre.format(max)}</span>
        </p>
        <ol className="m-0 flex h-48 list-none items-end gap-0.5 border-b border-bordure p-0" onMouseLeave={() => setActif(null)}>
          {serie.map((jour, index) => (
            <li
              key={jour.jour}
              tabIndex={0}
              aria-label={`${jourLong.format(date(jour.jour))} : ${jour.visiteurs} visiteurs, ${jour.pages} pages vues`}
              onMouseEnter={() => setActif(index)}
              onFocus={() => setActif(index)}
              onBlur={() => setActif(null)}
              className="group flex h-full min-w-0 flex-1 cursor-default items-end rounded-t-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lien"
            >
              <span
                aria-hidden="true"
                style={{ height: `${(jour.visiteurs / max) * 100}%` }}
                className={cx('block w-full rounded-t-[4px] bg-serie transition-opacity duration-150', jour.visiteurs === 0 && 'h-px! bg-bordure', actif !== null && actif !== index && 'opacity-50')}
              />
            </li>
          ))}
        </ol>
        {point ? (
          <div role="status" className="pointer-events-none absolute top-0 right-0 rounded-moyen border border-bordure bg-surface px-3 py-2 text-petit shadow-2">
            <p className="m-0 font-medium text-titre first-letter:uppercase">{jourLong.format(date(point.jour))}</p>
            <p className="m-0 text-texte">
              {compte(point.visiteurs, 'visiteur', 'visiteurs')} · {compte(point.pages, 'page vue', 'pages vues')}
            </p>
          </div>
        ) : null}
        <div aria-hidden="true" className="mt-1 flex justify-between text-petit text-texte-doux">
          {reperes.map((index) => (
            <span key={index}>{serie[index] ? jourCourt.format(date(serie[index].jour)) : ''}</span>
          ))}
        </div>
      </div>
      <details className="text-petit">
        <summary className="cursor-pointer text-lien">Voir le tableau jour par jour</summary>
        <table className="mt-2 w-full border-collapse">
          <thead>
            <tr className="text-left text-texte-doux">
              <th scope="col" className="py-1 font-medium">Jour</th>
              <th scope="col" className="py-1 text-right font-medium">Visiteurs</th>
              <th scope="col" className="py-1 text-right font-medium">Pages vues</th>
            </tr>
          </thead>
          <tbody>
            {[...serie].reverse().map((jour) => (
              <tr key={jour.jour} className="border-t border-bordure">
                <td className="py-1 text-texte">{jourLong.format(date(jour.jour))}</td>
                <td className="chiffres py-1 text-right text-titre">{nombre.format(jour.visiteurs)}</td>
                <td className="chiffres py-1 text-right text-texte">{nombre.format(jour.pages)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </details>
    </div>
  )
}

function Barres({ lignes, libelle }: { lignes: LigneAudience[]; libelle: (nom: string) => ReactNode }) {
  const max = Math.max(1, ...lignes.map((ligne) => ligne.visiteurs))
  return (
    <ol className="m-0 flex list-none flex-col gap-2.5 p-0">
      {lignes.map((ligne) => (
        <li key={ligne.nom} className="flex flex-col gap-1">
          <div className="flex items-baseline justify-between gap-3 text-petit">
            <span className="min-w-0 truncate text-titre">{libelle(ligne.nom)}</span>
            <span className="shrink-0 text-texte-doux">
              {compte(ligne.visiteurs, 'visiteur', 'visiteurs')} · {compte(ligne.pages, 'page', 'pages')}
            </span>
          </div>
          <span aria-hidden="true" className="block h-1.5 rounded-pastille bg-surface-douce">
            <span className="block h-full rounded-pastille bg-serie" style={{ width: `${(ligne.visiteurs / max) * 100}%` }} />
          </span>
        </li>
      ))}
    </ol>
  )
}

function Classement({ titre, icone, lignes, libelle, vide, apres }: { titre: string; icone: ReactNode; lignes: LigneAudience[]; libelle: (nom: string) => ReactNode; vide: string; apres?: ReactNode }) {
  return (
    <Carte as="section" className="flex flex-col gap-3">
      <h2 className="m-0 flex items-center gap-2 text-titre-3 text-titre">
        {icone}
        {titre}
      </h2>
      {lignes.length === 0 ? <p className="m-0 text-petit text-texte-doux">{vide}</p> : <Barres lignes={lignes} libelle={libelle} />}
      {apres}
    </Carte>
  )
}
