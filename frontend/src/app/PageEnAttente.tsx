import { EnTetePage } from '../ui'

type PageEnAttenteProps = {
  titre: string
}

/** Écran provisoire d'une route branchée plus tard (connexion, profil, tableau : voir brancherComptes). */
export function PageEnAttente({ titre }: PageEnAttenteProps) {
  return (
    <div className="mx-auto w-full max-w-site px-4 py-8 tablette:px-6 bureau:px-8">
      <EnTetePage titre={titre} description="Cet écran arrive dans une prochaine étape." />
    </div>
  )
}
