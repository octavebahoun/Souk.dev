import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ExternalLink, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react'
import { useCompte } from '../../app/compte'
import { useTitrePage } from '../../app/titre'
import {
  AccesRefuse,
  Alerte,
  Badge,
  Bouton,
  Carte,
  Champ,
  EnTetePage,
  EtatErreur,
  EtatVide,
  Etoiles,
  LienBouton,
  Liste,
  Modale,
  Saisie,
  SaisieMontant,
  SqueletteListe,
  ZoneTexte,
  formaterDate,
  formaterNote,
  useToast,
} from '../../ui'
import { messageErreur } from '../paiements/api'
import { formules } from '../paiements/formules'
import { BadgeSecurite } from './BadgeSecurite'
import { categories, fcfa, libelleCategorie, listerApplis, modifierAppli, relancerAnalyse, retirerAppli, type Appli, type Categorie } from './api'

/**
 * « Mes applis » : ce que le dev a publié, ce que le client paie et ce qui lui revient, avec les
 * actions de l'auteur (modifier la fiche, relancer l'analyse, retirer du Store).
 */
export function MesApplis() {
  useTitrePage('Mes applis')
  const compte = useCompte()
  const username = compte.data?.username ?? null
  const [aModifier, setAModifier] = useState<Appli | null>(null)
  const [aRetirer, setARetirer] = useState<Appli | null>(null)
  const applis = useQuery({
    queryKey: ['mes-applis', username],
    queryFn: () => listerApplis({ auteur: username ?? '', per_page: 50 }),
    enabled: username !== null,
    // Une analyse en cours se relit jusqu'à son badge.
    refetchInterval: (requete) => (requete.state.data?.data.some((appli) => appli.securite.statut === 'en_cours') ? 8000 : false),
  })

  const enTete = (
    <EnTetePage
      titre="Mes applis"
      description="Vos applis publiées sur le Store : leur fiche, leur prix et leur analyse de sécurité."
      actions={
        <LienBouton to="/publier" icone={<Plus aria-hidden="true" className="size-4" />}>
          Publier une appli
        </LienBouton>
      }
    />
  )

  if (compte.isPending || (username !== null && applis.isPending)) {
    return (
      <div className="flex flex-col gap-6">
        {enTete}
        <SqueletteListe nombre={3} />
      </div>
    )
  }

  if (!compte.data) {
    return <AccesRefuse description="Connectez-vous pour retrouver vos applis." action={<LienBouton to="/connexion">Se connecter</LienBouton>} />
  }

  if (username === null) {
    return (
      <div className="flex flex-col gap-6">
        {enTete}
        <EtatVide
          illustration="vide-store"
          titre="Liez GitHub pour publier"
          description="Une appli se publie depuis un dépôt GitHub public. Connectez-vous avec GitHub : votre profil devient un profil de dev."
          action={<LienBouton to="/connexion">Se connecter avec GitHub</LienBouton>}
        />
      </div>
    )
  }

  if (applis.isError || !applis.data) {
    return <EtatErreur onReessayer={() => void applis.refetch()} />
  }

  const liste = applis.data.data

  return (
    <div className="flex flex-col gap-6">
      {enTete}

      {liste.length === 0 ? (
        <EtatVide
          illustration="vide-store"
          titre="Vous n’avez pas encore publié d’appli"
          description="Liez un dépôt GitHub public : Souk.dev l’analyse, écrit les fichiers qui manquent et l’ouvre aux clients. Le prix que vous fixez vous revient en entier."
          action={<LienBouton to="/publier">Publier ma première appli</LienBouton>}
        />
      ) : (
        <ul className="m-0 flex list-none flex-col gap-4 p-0">
          {liste.map((appli) => (
            <li key={appli.id}>
              <LigneAppli appli={appli} onModifier={() => setAModifier(appli)} onRetirer={() => setARetirer(appli)} />
            </li>
          ))}
        </ul>
      )}

      {aModifier ? <ModaleModifier key={aModifier.id} appli={aModifier} onFermer={() => setAModifier(null)} /> : null}
      <ModaleRetirer appli={aRetirer} onFermer={() => setARetirer(null)} />
    </div>
  )
}

function LigneAppli({ appli, onModifier, onRetirer }: { appli: Appli; onModifier: () => void; onRetirer: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const formule = formules[appli.taille]
  const analyse = useMutation({
    mutationFn: () => relancerAnalyse(appli.id),
    onSuccess: async () => {
      toast.succes('Analyse relancée : le badge se met à jour dans quelques minutes.')
      await cache.invalidateQueries({ queryKey: ['mes-applis'] })
    },
    onError: (erreur) => toast.erreur(messageErreur(erreur, 'L’analyse n’a pas pu être relancée.')),
  })

  return (
    <Carte as="article" className="flex flex-col gap-4 tablette:flex-row">
      {appli.captures[0] ? (
        <img src={appli.captures[0].url} alt={`Capture de ${appli.nom}`} loading="lazy" className="aspect-video w-full shrink-0 rounded-petit border border-bordure bg-surface-douce object-cover object-top tablette:w-56" />
      ) : (
        <div aria-hidden="true" className="grid aspect-video w-full shrink-0 place-items-center rounded-petit border border-bordure bg-surface-accent text-titre-2 text-sur-primaire-doux tablette:w-56">
          {appli.nom.slice(0, 2).toUpperCase()}
        </div>
      )}

      <div className="flex min-w-0 flex-1 flex-col gap-3">
        <div className="flex flex-wrap items-center gap-1.5">
          <Badge ton="marque">{libelleCategorie(appli.categorie)}</Badge>
          <BadgeSecurite statut={appli.securite.statut} />
          <Badge>Version {appli.version}</Badge>
        </div>

        <div className="flex flex-col gap-1">
          <h2 className="m-0 text-titre-3">
            <Link to={`/store/${appli.id}`} className="text-titre no-underline hover:underline">
              {appli.nom}
            </Link>
          </h2>
          <p className="m-0 line-clamp-2 text-corps text-texte">{appli.description}</p>
        </div>

        <dl className="m-0 grid grid-cols-2 gap-3 text-petit bureau:grid-cols-4">
          <div>
            <dt className="text-texte-doux">Vous recevez</dt>
            <dd className="chiffres m-0 font-semibold text-titre">
              {appli.prix === 0 ? 'Gratuit' : `${fcfa(appli.prix)}${appli.type_prix === 'mensuel' ? ' / mois' : ' une fois'}`}
            </dd>
          </div>
          <div>
            <dt className="text-texte-doux">Le client paie</dt>
            <dd className="chiffres m-0 font-semibold text-titre">
              {fcfa(formule.prix + (appli.type_prix === 'mensuel' ? appli.prix : 0))} / mois
              {appli.prix > 0 && appli.type_prix === 'unique' ? ` + ${fcfa(appli.prix)} une fois` : ''}
            </dd>
          </div>
          <div>
            <dt className="text-texte-doux">Formule</dt>
            <dd className="m-0 font-medium text-titre">
              {formule.nom} · {formule.memoire}
            </dd>
          </div>
          <div>
            <dt className="text-texte-doux">Avis</dt>
            <dd className="m-0 flex items-center gap-1.5 font-medium text-titre">
              {appli.note_moyenne !== null ? (
                <>
                  <Etoiles note={appli.note_moyenne} taille="sm" />
                  <span>
                    {formaterNote(appli.note_moyenne)} ({appli.nb_avis})
                  </span>
                </>
              ) : (
                'Pas encore noté'
              )}
            </dd>
          </div>
        </dl>

        {appli.securite.statut === 'problemes' ? (
          <Alerte ton="alerte">
            L’analyse a trouvé {appli.securite.problemes.length} dépendance{appli.securite.problemes.length > 1 ? 's' : ''} à mettre à jour. Votre appli passe après les autres dans le Store tant qu’elles restent :
            corrigez-les, poussez sur GitHub, puis relancez l’analyse.
          </Alerte>
        ) : null}

        <div className="flex flex-wrap items-center gap-2 border-t border-bordure pt-3">
          <LienBouton to={`/store/${appli.id}`} variante="contour" taille="sm" icone={<ExternalLink aria-hidden="true" className="size-3.5" />}>
            Voir la fiche
          </LienBouton>
          <Bouton variante="contour" taille="sm" icone={<Pencil aria-hidden="true" className="size-3.5" />} onClick={onModifier}>
            Modifier
          </Bouton>
          <Bouton
            variante="contour"
            taille="sm"
            icone={<RefreshCw aria-hidden="true" className="size-3.5" />}
            chargement={analyse.isPending}
            disabled={appli.securite.statut === 'en_cours'}
            onClick={() => analyse.mutate()}
          >
            Relancer l’analyse
          </Bouton>
          <Bouton variante="retrait" taille="sm" icone={<Trash2 aria-hidden="true" className="size-3.5" />} onClick={onRetirer} className="tablette:ml-auto">
            Retirer du Store
          </Bouton>
          <span className="w-full text-petit text-texte-doux">Publiée le {formaterDate(appli.cree_le)}</span>
        </div>
      </div>
    </Carte>
  )
}

function ModaleModifier({ appli, onFermer }: { appli: Appli; onFermer: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const [nom, setNom] = useState(appli.nom)
  const [description, setDescription] = useState(appli.description)
  const [categorie, setCategorie] = useState<Categorie>(appli.categorie)
  const [demo, setDemo] = useState(appli.demo_url)
  const [prix, setPrix] = useState(String(appli.prix))
  const [champs, setChamps] = useState<Record<string, string>>({})
  const montant = prix.replace(/\s/g, '') === '' ? 0 : Number(prix.replace(/\s/g, ''))

  const modifier = useMutation({
    mutationFn: () => modifierAppli(appli.id, { nom: nom.trim(), description: description.trim(), categorie, demo_url: demo.trim(), prix: montant }),
    onSuccess: async () => {
      toast.succes('Fiche mise à jour.')
      await Promise.all([cache.invalidateQueries({ queryKey: ['mes-applis'] }), cache.invalidateQueries({ queryKey: ['appli', appli.id] })])
      onFermer()
    },
  })

  function enregistrer(evenement: FormEvent) {
    evenement.preventDefault()
    const suivants: Record<string, string> = {}
    if (!nom.trim()) suivants.nom = 'Donnez un nom à votre appli.'
    if (!description.trim()) suivants.description = 'Décrivez ce que fait votre appli.'
    if (!/^https:\/\/.+/i.test(demo.trim())) suivants.demo_url = 'Le lien de démo doit commencer par https://.'
    if (!Number.isInteger(montant) || montant < 0) suivants.prix = 'Le prix est un nombre entier de francs, 0 pour une appli gratuite.'
    setChamps(suivants)
    if (Object.keys(suivants).length > 0) return
    modifier.mutate()
  }

  return (
    <Modale
      ouverte
      onFermer={onFermer}
      titre={`Modifier ${appli.nom}`}
      description="Le nouveau prix vaut pour les prochaines commandes ; les clients déjà installés gardent le leur."
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton type="submit" form="modifier-appli" chargement={modifier.isPending}>
            Enregistrer
          </Bouton>
        </>
      }
    >
      <form id="modifier-appli" onSubmit={enregistrer} className="flex flex-col gap-4" noValidate>
        <Champ libelle="Nom" erreur={champs.nom}>
          {(liaison) => <Saisie {...liaison} value={nom} maxLength={100} onChange={(evenement) => setNom(evenement.target.value)} data-autofocus />}
        </Champ>
        <Champ libelle="Description" erreur={champs.description} aide="Ce que l’appli fait pour le client, en mots simples.">
          {(liaison) => <ZoneTexte {...liaison} rows={4} maxLength={1000} value={description} onChange={(evenement) => setDescription(evenement.target.value)} />}
        </Champ>
        <div className="grid gap-4 tablette:grid-cols-2">
          <Champ libelle="Catégorie">
            {(liaison) => (
              <Liste {...liaison} value={categorie} onChange={(evenement) => setCategorie(evenement.target.value as Categorie)}>
                {categories.map((item) => (
                  <option key={item.valeur} value={item.valeur}>
                    {item.libelle}
                  </option>
                ))}
              </Liste>
            )}
          </Champ>
          <Champ libelle={appli.type_prix === 'mensuel' ? 'Prix par mois' : 'Prix, payé une fois'} erreur={champs.prix} aide="0 pour une appli gratuite. Il vous revient en entier.">
            {(liaison) => <SaisieMontant {...liaison} value={prix} onChange={(evenement) => setPrix(evenement.target.value.replace(/[^0-9 ]/g, ''))} />}
          </Champ>
        </div>
        <Champ libelle="Lien de démo" erreur={champs.demo_url} aide="Une adresse https où le client essaie l’appli avant de commander.">
          {(liaison) => <Saisie {...liaison} value={demo} onChange={(evenement) => setDemo(evenement.target.value)} className="font-mono text-petit" />}
        </Champ>
        {modifier.isError ? <Alerte ton="danger">{messageErreur(modifier.error, 'La fiche n’a pas été enregistrée.')}</Alerte> : null}
      </form>
    </Modale>
  )
}

function ModaleRetirer({ appli, onFermer }: { appli: Appli | null; onFermer: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const retirer = useMutation({
    mutationFn: (id: number) => retirerAppli(id),
    onSuccess: async () => {
      toast.succes('Appli retirée du Store.')
      await Promise.all([cache.invalidateQueries({ queryKey: ['mes-applis'] }), cache.invalidateQueries({ queryKey: ['applis'] })])
      onFermer()
    },
  })

  return (
    <Modale
      ouverte={appli !== null}
      onFermer={onFermer}
      taille="petite"
      titre="Retirer cette appli du Store ?"
      description={
        appli
          ? `« ${appli.nom} » ne sera plus visible ni commandable. Les copies déjà en ligne chez vos clients continuent de tourner et de vous rapporter.`
          : undefined
      }
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Garder l’appli
          </Bouton>
          <Bouton variante="danger" chargement={retirer.isPending} onClick={() => appli && retirer.mutate(appli.id)}>
            Retirer du Store
          </Bouton>
        </>
      }
    >
      {retirer.isError ? <Alerte ton="danger">{messageErreur(retirer.error, 'L’appli n’a pas pu être retirée.')}</Alerte> : null}
    </Modale>
  )
}
