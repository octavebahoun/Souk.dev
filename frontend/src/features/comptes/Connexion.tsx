import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { isAxiosError } from 'axios'
import { ArrowRight, CircleCheck, GitBranch, Mail } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { demanderLien } from './api'
import { lireErreurHttp, messageAction } from '../echange/api'
import { Alerte, Bouton, Carte, Champ, Saisie, classesBouton, cx } from '../../ui'
import hero from '../../assets/accueil/hero.webp'

type ProfilChoix = 'dev' | 'client'

const profils: { valeur: ProfilChoix; libelle: string }[] = [
  { valeur: 'dev', libelle: 'Développeur' },
  { valeur: 'client', libelle: 'Entreprise ou particulier' },
]

/**
 * Connexion : un seul compte, deux entrées. Les devs passent par GitHub (dépôts publics),
 * les entreprises, agences et particuliers reçoivent un lien par e-mail, sans mot de passe.
 */
export function Connexion() {
  useTitrePage('Se connecter')
  const [params] = useSearchParams()
  const [profil, setProfil] = useState<ProfilChoix>('dev')
  const [email, setEmail] = useState('')
  const [envoye, setEnvoye] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const dev = profil === 'dev'
  const erreurOAuth =
    params.get('erreur') === 'github_non_configure'
      ? 'GitHub OAuth n’est pas configuré. Ajoutez le Client ID et le Client secret dans backend/.env, puis relancez Laravel.'
      : params.get('erreur') === 'compte_suspendu'
        ? 'Votre compte est suspendu : la connexion est refusée. Le motif est dans l’e-mail reçu ; pour contester, répondez-y.'
        : params.has('erreur')
          ? 'La connexion GitHub n’a pas abouti. Vérifiez l’application OAuth et son URL de rappel, puis réessayez.'
          : null

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.trim())) {
      setErreur('Cette adresse ne semble pas valide : vérifiez le @ et le domaine.')
      return
    }
    setEnvoi(true)
    setErreur(null)
    try {
      await demanderLien(email.trim())
      setEnvoye(true)
    } catch (erreurHttp) {
      const lu = lireErreurHttp(erreurHttp, 'L’envoi a échoué.')
      if (isAxiosError(erreurHttp) && erreurHttp.response?.status === 429) {
        setErreur('Trop de liens demandés. Réessayez dans quelques minutes.')
      } else if (isAxiosError(erreurHttp) && erreurHttp.request && !erreurHttp.response) {
        setErreur(messageAction(erreurHttp, 'L’envoi du lien a échoué.'))
      } else {
        setErreur(lu.champs.email ?? lu.message)
      }
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="mx-auto grid w-full max-w-site gap-8 px-4 py-8 tablette:px-6 tablette:py-12 bureau:grid-cols-2 bureau:items-stretch bureau:px-8">
      <section
        aria-label="Souk.dev en un compte"
        className="relative isolate hidden overflow-hidden rounded-grand bg-nuit p-10 text-sur-couleur bureau:flex bureau:flex-col bureau:justify-end"
      >
        <img src={hero} alt="" width={1376} height={768} className="absolute inset-0 -z-10 size-full object-cover object-right opacity-70" />
        <div aria-hidden="true" className="absolute inset-0 -z-10 bg-linear-to-t from-nuit via-nuit/80 to-nuit/10" />
        <div className="flex flex-col gap-4">
          <p className="m-0 text-titre-1 text-sur-couleur">Le marché des devs africains, en un seul compte.</p>
          <p className="m-0 text-corps-l text-sur-couleur/85">Les devs échangent et publient leurs applis. Les entreprises trouvent l’outil, ou le dev, qu’il leur faut.</p>
          <ul className="m-0 flex list-none flex-col gap-2 p-0 text-corps text-sur-couleur/85">
            <li className="flex items-center gap-2"><CircleCheck aria-hidden="true" className="size-4 text-vert-marque" />Dev : vous arrivez sur l’Échange</li>
            <li className="flex items-center gap-2"><CircleCheck aria-hidden="true" className="size-4 text-vert-marque" />Entreprise : vous arrivez sur le Store</li>
          </ul>
        </div>
      </section>

      <div className="flex items-center justify-center bureau:py-10">
        <Carte marge="large" className="flex w-full max-w-md flex-col gap-6">
          <div className="flex flex-col gap-1">
            <h1 className="m-0 text-titre-1 text-titre">Se connecter</h1>
            <p className="m-0 text-corps text-texte-doux">Ou créer votre compte : c’est la même porte.</p>
          </div>

          <div role="group" aria-label="Votre profil" className="grid grid-cols-2 gap-1 rounded-moyen border border-bordure bg-surface-douce p-1">
            {profils.map((choix) => (
              <button
                key={choix.valeur}
                type="button"
                aria-pressed={profil === choix.valeur}
                onClick={() => {
                  setProfil(choix.valeur)
                  setErreur(null)
                }}
                className={cx(
                  'min-h-11 rounded-petit px-2 text-petit font-medium transition-colors duration-150',
                  profil === choix.valeur ? 'bg-primaire-doux text-sur-primaire-doux shadow-1' : 'text-texte-doux hover:text-titre',
                )}
              >
                {choix.libelle}
              </button>
            ))}
          </div>

          {dev ? (
            <div className="flex flex-col gap-3">
              {erreurOAuth ? <Alerte ton="danger">{erreurOAuth}</Alerte> : null}
              <a href="/auth/github?profil=dev" className={classesBouton('primaire', 'lg', true)}>
                <GitBranch aria-hidden="true" className="size-4" />
                Continuer avec GitHub
              </a>
              <p className="m-0 text-petit text-texte-doux">Dépôts publics uniquement : on ne demande jamais l’accès à vos dépôts privés.</p>
            </div>
          ) : envoye ? (
            <Alerte
              ton="succes"
              titre={`Lien envoyé à ${email.trim()}`}
              action={
                <Bouton variante="fantome" taille="sm" onClick={() => setEnvoye(false)}>
                  Changer d’adresse
                </Bouton>
              }
            >
              Ouvrez-le depuis cet appareil. Il ne sert qu’une seule fois.
            </Alerte>
          ) : (
            <form onSubmit={(evenement) => void envoyer(evenement)} className="flex flex-col gap-4" noValidate>
              <Champ libelle="Votre e-mail" erreur={erreur} aide="Un lien de connexion, sans mot de passe.">
                {(liaison) => (
                  <Saisie
                    {...liaison}
                    type="email"
                    autoComplete="email"
                    value={email}
                    onChange={(evenement) => {
                      setEmail(evenement.target.value)
                      setErreur(null)
                    }}
                    placeholder="vous@entreprise.bj"
                  />
                )}
              </Champ>
              <Bouton type="submit" taille="lg" pleineLargeur chargement={envoi} icone={<Mail aria-hidden="true" className="size-4" />}>
                Recevoir mon lien
              </Bouton>
            </form>
          )}

          <p className="m-0 flex items-center gap-1.5 border-t border-bordure pt-4 text-petit text-texte-doux">
            Pas encore décidé ?
            <Link to="/store" className="inline-flex items-center gap-1 font-medium">
              Parcourir le Store <ArrowRight aria-hidden="true" className="size-3.5" />
            </Link>
          </p>
        </Carte>
      </div>
    </div>
  )
}
