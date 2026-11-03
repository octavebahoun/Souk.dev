import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { ShieldAlert, ShieldCheck, ShieldEllipsis, ShieldX } from 'lucide-react'
import type { components } from '../../shared/types/api'
import { cheminLaravel, clientLocal } from '../../shared/client'
import { preparerCsrf } from '../../app/preparerCsrf'
import { Alerte, Bouton, Carte, PastilleEtat, Tableau, formaterDate, type Colonne } from '../../ui'
import { messageErreur } from '../paiements/api'

type Securite = components['schemas']['Securite']
type Probleme = components['schemas']['ProblemeSecurite']

const gravites: Record<Probleme['gravite'], { ton: 'danger' | 'alerte' | 'neutre'; libelle: string }> = {
  critique: { ton: 'danger', libelle: 'Critique' },
  haute: { ton: 'danger', libelle: 'Haute' },
  moyenne: { ton: 'alerte', libelle: 'Moyenne' },
  faible: { ton: 'neutre', libelle: 'Faible' },
}

async function relancer(appId: number): Promise<Securite> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Securite>(cheminLaravel(`/apps/${appId}/securite`))
  return data
}

/**
 * Sécurité avant publication, vue par tous sur la fiche : aucun secret dans le dépôt (sinon la
 * publication est refusée), et les dépendances aux failles connues (Trivy). L'auteur relance
 * l'analyse après avoir mis ses dépendances à jour.
 */
export function RapportSecurite({ appId, securite, estAuteur }: { appId: number; securite: Securite; estAuteur: boolean }) {
  const cache = useQueryClient()
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)

  const colonnes: Colonne<Probleme>[] = [
    { cle: 'paquet', entete: 'Dépendance', principale: true, rendu: (probleme) => <span className="font-mono text-petit text-titre">{probleme.paquet}</span> },
    { cle: 'version', entete: 'Version', rendu: (probleme) => <span className="font-mono text-petit">{probleme.version}</span> },
    {
      cle: 'gravite',
      entete: 'Gravité',
      rendu: (probleme) => <PastilleEtat ton={gravites[probleme.gravite].ton} libelle={gravites[probleme.gravite].libelle} />,
    },
    { cle: 'faille', entete: 'Faille', rendu: (probleme) => <span className="font-mono text-petit">{probleme.identifiant}</span> },
    { cle: 'correction', entete: 'Corrigée en', rendu: (probleme) => probleme.version_corrigee ?? 'pas encore' },
  ]

  async function lancer() {
    setEnvoi(true)
    setErreur(null)
    try {
      await relancer(appId)
      await cache.invalidateQueries({ queryKey: ['appli', appId] })
    } catch (probleme) {
      setErreur(messageErreur(probleme, 'L’analyse n’a pas pu être relancée. Réessayez dans un instant.'))
    } finally {
      setEnvoi(false)
    }
  }

  const presentation = {
    verifie: { titre: 'Security Checked', Icone: ShieldCheck, classe: 'bg-fond-succes text-succes' },
    problemes: { titre: 'Problèmes détectés', Icone: ShieldAlert, classe: 'bg-fond-alerte text-alerte' },
    en_cours: { titre: 'Analyse en cours', Icone: ShieldEllipsis, classe: 'bg-fond-info text-info' },
    echec: { titre: 'Analyse impossible', Icone: ShieldX, classe: 'bg-fond-danger text-danger' },
  }[securite.statut]
  const { titre, Icone } = presentation
  const nombre = securite.problemes.length

  return (
    <Carte as="section" aria-labelledby="titre-securite" className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          <span
            aria-hidden="true"
            className={`grid size-10 shrink-0 place-items-center rounded-moyen ${presentation.classe}`}
          >
            <Icone className="size-5" />
          </span>
          <div className="flex flex-col gap-0.5">
            <h2 id="titre-securite" className="m-0 text-titre-3 text-titre">
              Sécurité : {titre}
            </h2>
            <p className="m-0 text-petit text-texte-doux">
              {securite.statut === 'en_cours'
                ? 'Les dépendances du dépôt sont en cours d’analyse. Le résultat s’affiche ici dans quelques minutes.'
                : securite.statut === 'echec'
                  ? 'Le dépôt n’a pas pu être analysé (devenu inaccessible, ou outil d’analyse indisponible). Aucun secret n’avait été trouvé à la publication.'
                  : `Analysée le ${formaterDate(securite.analyse_le ?? '', 'avecHeure')}. Aucun secret dans le dépôt (sinon la publication est refusée).`}
            </p>
          </div>
        </div>
        {estAuteur ? (
          <Bouton variante="contour" taille="sm" chargement={envoi} onClick={() => void lancer()}>
            Relancer l’analyse
          </Bouton>
        ) : null}
      </div>

      {securite.statut === 'problemes' ? (
        <>
          <p className="m-0 text-corps text-texte">
            {nombre} dépendance{nombre > 1 ? 's ont' : ' a'} une faille connue.
            {estAuteur
              ? ` Mettez-${nombre > 1 ? 'les' : 'la'} à jour vers la version corrigée, puis relancez l’analyse.`
              : ` L’auteur est invité à ${nombre > 1 ? 'les' : 'la'} mettre à jour.`}
          </p>
          <Tableau legende="Dépendances avec une faille connue" colonnes={colonnes} lignes={securite.problemes} cleLigne={(probleme) => `${probleme.identifiant}-${probleme.paquet}`} />
        </>
      ) : null}

      {erreur ? (
        <Alerte ton="danger" urgente>
          {erreur}
        </Alerte>
      ) : null}
    </Carte>
  )
}
