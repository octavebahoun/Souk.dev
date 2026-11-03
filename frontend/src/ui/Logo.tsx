import { cx } from './cx'

type LogoProps = {
  /** Marque seule (favicon, mobile serré) ou marque et nom. */
  variante?: 'complet' | 'marque'
  /** Sur fond sombre (hero, pied de page nuit) : le nom passe en blanc. */
  surFondSombre?: boolean
  className?: string
}

/**
 * Logo de Souk.dev : un « S » fait de deux chevrons de code, et le point de « .dev »
 * en vert menthe. Dessiné pour Souk.dev (aucune reprise d'un autre logo).
 */
export function Logo({ variante = 'complet', surFondSombre = false, className }: LogoProps) {
  return (
    <span className={cx('inline-flex items-center gap-2', className)}>
      <svg viewBox="0 0 32 32" aria-hidden="true" className="size-8 shrink-0">
        <rect width="32" height="32" rx="8" className="fill-primaire" />
        <path d="M20.2 7.6 11.6 13.3l9 5.7-8.4 5.6" fill="none" strokeWidth="3.2" strokeLinecap="round" strokeLinejoin="round" className="stroke-sur-couleur" />
        <circle cx="22.7" cy="24.6" r="2.3" className="fill-vert-marque" />
      </svg>
      {variante === 'complet' ? (
        <span className={cx('text-logo', surFondSombre ? 'text-sur-couleur' : 'text-titre')}>
          Souk<span className="text-vert-marque">.</span>
          <span className={surFondSombre ? 'text-sur-couleur/80' : 'text-primaire dark:text-lien'}>dev</span>
        </span>
      ) : (
        <span className="pour-lecteur">Souk.dev</span>
      )}
    </span>
  )
}
