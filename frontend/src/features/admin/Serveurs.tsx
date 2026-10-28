import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { RefreshCw } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import {
  AccesRefuse,
  Alerte,
  Bouton,
  Carte,
  Champ,
  EnTetePage,
  EtatErreur,
  LienBouton,
  Modale,
  PastilleEtat,
  Saisie,
  Squelette,
  cx,
  formaterIlYa,
  useToast,
} from '../../ui'
import { erreursDeChamps, messageErreur } from '../paiements/api'
import { creerMachine, listerMachines, modifierMachine, sonderMachine, supprimerMachine, type Machine } from './api'
import { formaterMemoire, partUtilisee, presentationEtat, texteCopies, texteMemoire, tonJauge } from './etatServeurs'

/**
 * Serveurs du moteur (admin) : la flotte, sa santé sondée par le moteur, et son entretien —
 * enregistrer un démon, le mettre en maintenance (drain : il garde ses copies mais n'en reçoit
 * plus), le sonder, le retirer. Chaque nouvelle copie part sur le serveur le plus libre.
 */
export function Serveurs() {
  useTitrePage('Serveurs')
  const machines = useQuery({ queryKey: ['admin', 'machines'], queryFn: listerMachines, refetchInterval: 15_000 })

  if (machines.isError && axios.isAxiosError(machines.error) && machines.error.response?.status === 403) {
    return <AccesRefuse description="Les serveurs sont réservés à l’équipe de Souk.dev." action={<LienBouton to="/tableau">Retour à mon espace</LienBouton>} />
  }

  return (
    <div className="flex flex-col gap-8">
      <EnTetePage titre="Serveurs" description="Les démons Docker qui hébergent les copies. Chacune part sur le serveur actif et joignable qui a le plus de mémoire libre." />

      {machines.isPending ? (
        <div role="status" aria-label="Chargement des serveurs" className="grid gap-4 bureau:grid-cols-2">
          <Squelette className="h-52 rounded-moyen" />
          <Squelette className="h-52 rounded-moyen" />
        </div>
      ) : machines.isError || !machines.data ? (
        <EtatErreur onReessayer={() => void machines.refetch()} />
      ) : machines.data.length === 0 ? (
        <p className="m-0 rounded-moyen border border-dashed border-bordure p-6 text-center text-texte-doux">Aucun serveur enregistré. La machine locale se recrée à la prochaine copie.</p>
      ) : (
        <ul aria-label="Serveurs" className="m-0 grid list-none gap-4 p-0 bureau:grid-cols-2">
          {machines.data.map((machine) => (
            <li key={machine.id}>
              <CarteMachine machine={machine} />
            </li>
          ))}
        </ul>
      )}

      <AjouterServeur />
    </div>
  )
}

function CarteMachine({ machine }: { machine: Machine }) {
  const cache = useQueryClient()
  const toast = useToast()
  const [cible, setCible] = useState<'maintenance' | 'retrait' | 'modification' | null>(null)
  const etat = presentationEtat(machine)
  const part = partUtilisee(machine)
  const rafraichir = () => cache.invalidateQueries({ queryKey: ['admin', 'machines'] })

  const sonde = useMutation({
    mutationFn: () => sonderMachine(machine.id),
    onSuccess: () => {
      toast.succes(`Sonde de ${machine.nom} demandée au moteur.`)
      void rafraichir()
    },
    onError: (probleme) => toast.erreur(messageErreur(probleme, 'La sonde n’a pas pu être demandée.')),
  })

  const bascule = useMutation({
    mutationFn: () => modifierMachine(machine.id, { etat: machine.etat === 'maintenance' ? 'active' : 'maintenance' }),
    onSuccess: (modifiee) => {
      toast.succes(modifiee.etat === 'maintenance' ? `${machine.nom} ne reçoit plus de nouvelles copies.` : `${machine.nom} reçoit de nouveau des copies.`)
      setCible(null)
      void rafraichir()
    },
    onError: (probleme) => toast.erreur(messageErreur(probleme, 'La bascule n’a pas pu être enregistrée.')),
  })

  const retrait = useMutation({
    mutationFn: () => supprimerMachine(machine.id),
    onSuccess: () => {
      toast.succes(`${machine.nom} retiré.`)
      setCible(null)
      void rafraichir()
    },
  })

  return (
    <Carte as="article" aria-label={machine.nom} className="flex h-full flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="flex flex-col gap-1">
          <h2 className="m-0 text-titre-3">{machine.nom}</h2>
          <p className="m-0 font-mono text-petit text-texte-doux">{machine.hote_docker ?? 'démon local'}</p>
        </div>
        <PastilleEtat ton={etat.ton} libelle={etat.libelle} enCours={machine.sondee_le === null} />
      </div>

      <div className="flex flex-col gap-1" aria-label={`Mémoire de ${machine.nom}`}>
        <p className="m-0 text-petit text-texte-doux">{texteMemoire(machine)}</p>
        {part === null ? null : (
          <div role="progressbar" aria-valuenow={Math.round(part * 100)} aria-valuemin={0} aria-valuemax={100} aria-label="Mémoire réservée" className="h-2 overflow-hidden rounded-pastille bg-surface-douce">
            <div
              className={cx('h-full rounded-pastille transition-[width] duration-300', tonJauge(machine) === 'danger' ? 'bg-danger' : tonJauge(machine) === 'alerte' ? 'bg-alerte' : 'bg-succes')}
              style={{ width: `${Math.round(part * 100)}%` }}
            />
          </div>
        )}
      </div>

      <p className="m-0 text-petit text-texte-doux">
        {texteCopies(machine.copies)}
        {machine.version_docker ? ` · Docker ${machine.version_docker}` : ''}
        {machine.memoire_totale_mo !== null ? ` · ${formaterMemoire(machine.memoire_totale_mo)} au total` : ''}
      </p>
      <p className="m-0 text-petit text-texte-doux">{machine.sondee_le === null ? 'Sonde du moteur en cours…' : `Sondé ${formaterIlYa(machine.sondee_le)}`}</p>
      {machine.sonde_erreur ? <Alerte ton="danger">{machine.sonde_erreur}</Alerte> : null}

      <div className="mt-auto flex flex-wrap gap-2 border-t border-bordure pt-4">
        <Bouton variante="contour" taille="sm" chargement={sonde.isPending} onClick={() => sonde.mutate()} icone={<RefreshCw aria-hidden="true" className="size-4" />}>
          Sonder
        </Bouton>
        {machine.etat === 'maintenance' ? (
          <Bouton variante="contour" taille="sm" chargement={bascule.isPending} onClick={() => bascule.mutate()}>
            Remettre en service
          </Bouton>
        ) : (
          <Bouton variante="contour" taille="sm" onClick={() => setCible('maintenance')}>
            Mettre en maintenance
          </Bouton>
        )}
        <Bouton variante="fantome" taille="sm" onClick={() => setCible('modification')}>
          Modifier
        </Bouton>
        <Bouton variante="fantome" taille="sm" className="text-danger" onClick={() => setCible('retrait')}>
          Retirer
        </Bouton>
      </div>

      <Modale
        ouverte={cible === 'maintenance'}
        onFermer={() => setCible(null)}
        taille="petite"
        titre={`Mettre ${machine.nom} en maintenance ?`}
        description="Il gardera ses copies jusqu’à leur arrêt, mais plus aucune nouvelle copie n’y partira. Réversible à tout moment."
        pied={
          <>
            <Bouton variante="contour" onClick={() => setCible(null)}>
              Annuler
            </Bouton>
            <Bouton chargement={bascule.isPending} onClick={() => bascule.mutate()}>
              Mettre en maintenance
            </Bouton>
          </>
        }
      >
        <p className="m-0 text-petit text-texte-doux">
          {texteCopies(machine.copies)} concernée{machine.copies > 1 ? 's' : ''} : elles continuent de tourner, surveillées comme avant.
        </p>
      </Modale>

      {cible === 'modification' ? <ModifierServeur machine={machine} onFermer={() => setCible(null)} /> : null}

      <Modale
        ouverte={cible === 'retrait'}
        onFermer={() => setCible(null)}
        taille="petite"
        titre={`Retirer ${machine.nom} ?`}
        description="Le serveur ne recevra plus rien. Refusé tant qu’une copie y tourne : arrêtez-les d’abord."
        pied={
          <>
            <Bouton variante="contour" onClick={() => setCible(null)}>
              Annuler
            </Bouton>
            <Bouton variante="danger" chargement={retrait.isPending} onClick={() => retrait.mutate()}>
              Retirer
            </Bouton>
          </>
        }
      >
        {retrait.isError ? <Alerte ton="danger">{messageErreur(retrait.error, 'Le serveur n’a pas pu être retiré.')}</Alerte> : null}
      </Modale>
    </Carte>
  )
}

function ModifierServeur({ machine, onFermer }: { machine: Machine; onFermer: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const [nom, setNom] = useState(machine.nom)
  const [hote, setHote] = useState(machine.hote_docker ?? '')
  const [capacite, setCapacite] = useState(machine.capacite_mo === null ? '' : String(machine.capacite_mo))
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [erreur, setErreur] = useState<string | null>(null)

  const modification = useMutation({
    mutationFn: () =>
      modifierMachine(machine.id, {
        nom: nom.trim(),
        hote_docker: hote.trim() === '' ? null : hote.trim(),
        capacite_mo: capacite.trim() === '' ? null : Number(capacite),
      }),
    onSuccess: (modifiee) => {
      toast.succes(`${modifiee.nom} enregistré.`)
      onFermer()
      void cache.invalidateQueries({ queryKey: ['admin', 'machines'] })
    },
    onError: (probleme) => {
      setErreurs(erreursDeChamps(probleme))
      setErreur(messageErreur(probleme, 'Les modifications n’ont pas pu être enregistrées.'))
    },
  })

  return (
    <Modale
      ouverte
      onFermer={onFermer}
      titre={`Modifier ${machine.nom}`}
      description="Nom, démon visé et mémoire réservée aux copies."
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton chargement={modification.isPending} disabled={nom.trim() === ''} onClick={() => modification.mutate()}>
            Enregistrer
          </Bouton>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <Champ libelle="Nom" erreur={erreurs.nom}>{(liaison) => <Saisie {...liaison} value={nom} maxLength={64} onChange={(evenement) => setNom(evenement.target.value)} />}</Champ>
        <Champ libelle="Démon Docker (DOCKER_HOST)" aide="Vide : démon local. Ne change pas tant que des copies tournent dessus." erreur={erreurs.hote_docker}>
          {(liaison) => <Saisie {...liaison} value={hote} maxLength={255} placeholder="tcp://10.0.0.2:2375" onChange={(evenement) => setHote(evenement.target.value)} />}
        </Champ>
        <Champ libelle="Capacité mémoire (Mo)" aide="Mémoire réservée aux copies. Vide : sans limite déclarée." erreur={erreurs.capacite_mo}>
          {(liaison) => <Saisie {...liaison} value={capacite} inputMode="numeric" placeholder="8192" onChange={(evenement) => setCapacite(evenement.target.value.replace(/\D/g, ''))} />}
        </Champ>
        {erreur && Object.keys(erreurs).length === 0 ? <Alerte ton="danger">{erreur}</Alerte> : null}
      </div>
    </Modale>
  )
}

function AjouterServeur() {
  const cache = useQueryClient()
  const toast = useToast()
  const [nom, setNom] = useState('')
  const [hote, setHote] = useState('')
  const [capacite, setCapacite] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [erreur, setErreur] = useState<string | null>(null)

  const ajout = useMutation({
    mutationFn: () =>
      creerMachine({
        nom: nom.trim(),
        hote_docker: hote.trim() === '' ? null : hote.trim(),
        capacite_mo: capacite.trim() === '' ? null : Number(capacite),
      }),
    onSuccess: (machine) => {
      toast.succes(`${machine.nom} enregistré, le moteur le sonde.`)
      setNom('')
      setHote('')
      setCapacite('')
      setErreurs({})
      setErreur(null)
      void cache.invalidateQueries({ queryKey: ['admin', 'machines'] })
    },
    onError: (probleme) => {
      setErreurs(erreursDeChamps(probleme))
      setErreur(messageErreur(probleme, 'Le serveur n’a pas pu être enregistré.'))
    },
  })

  return (
    <section aria-labelledby="titre-ajout" className="flex flex-col gap-4">
      <h2 id="titre-ajout" className="m-0 text-titre-2">
        Ajouter un serveur
      </h2>
      <Carte className="flex flex-col gap-4">
        <div className="grid gap-4 tablette:grid-cols-3">
          <Champ libelle="Nom" erreur={erreurs.nom}>{(liaison) => <Saisie {...liaison} value={nom} maxLength={64} placeholder="aux" onChange={(evenement) => setNom(evenement.target.value)} />}</Champ>
          <Champ libelle="Démon Docker (DOCKER_HOST)" aide="Vide : démon local." erreur={erreurs.hote_docker}>
            {(liaison) => <Saisie {...liaison} value={hote} maxLength={255} placeholder="tcp://10.0.0.2:2375" onChange={(evenement) => setHote(evenement.target.value)} />}
          </Champ>
          <Champ libelle="Capacité mémoire (Mo)" aide="Vide : sans limite déclarée." erreur={erreurs.capacite_mo}>
            {(liaison) => <Saisie {...liaison} value={capacite} inputMode="numeric" placeholder="8192" onChange={(evenement) => setCapacite(evenement.target.value.replace(/\D/g, ''))} />}
          </Champ>
        </div>
        {erreur && Object.keys(erreurs).length === 0 ? <Alerte ton="danger">{erreur}</Alerte> : null}
        <div>
          <Bouton chargement={ajout.isPending} disabled={nom.trim() === ''} onClick={() => ajout.mutate()}>
            Ajouter le serveur
          </Bouton>
        </div>
      </Carte>
    </section>
  )
}
