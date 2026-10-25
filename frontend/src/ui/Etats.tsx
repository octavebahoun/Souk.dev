import { useEffect, useState, type ReactNode } from 'react'
import { CircleAlert, CircleCheck, Info, Sparkles, TriangleAlert, WifiOff } from 'lucide-react'
import { t } from '../i18n'
import { Bouton } from './Bouton'
import { cx } from './cx'
import { Illustration } from './Illustration'
import type { NomIllustration } from './illustrations'

type EtatVideProps = {
  illustration: NomIllustration
  titre: ReactNode
  description?: ReactNode
  /** L'action pour sortir de l'état vide (un LienBouton ou un Bouton). */
  action?: ReactNode
  actionSecondaire?: ReactNode
  /** Niveau du titre dans la page (h2 par défaut, h1 pour une page entière). */
  niveau?: 1 | 2 | 3
  className?: string
}

/** État vide, d'erreur ou d'accès : illustration, une phrase qui explique, l'action pour en sortir. */
export function EtatVide({ illustration, titre, description, action, actionSecondaire, niveau = 2, className }: EtatVideProps) {
  const Titre = (`h${niveau}` as 'h1' | 'h2' | 'h3')
  return (
    <div className={cx('flex flex-col items-center gap-5 px-4 py-10 text-center tablette:py-14', className)}>
      <Illustration nom={illustration} />
      <div className="flex max-w-md flex-col gap-2">
        <Titre className="m-0 text-titre-2">{titre}</Titre>
        {description ? <p className="m-0 text-balance text-texte-doux">{description}</p> : null}
      </div>
      {action || actionSecondaire ? (
        <div className="flex flex-col items-center gap-2 tablette:flex-row">
          {action}
          {actionSecondaire}
        </div>
      ) : null}
    </div>
  )
}

type EtatErreurProps = {
  titre?: ReactNode
  description?: ReactNode
  onReessayer?: () => void
  className?: string
}

export function EtatErreur({ titre = t.etats.erreurTitre, description = t.etats.erreurTexte, onReessayer, className }: EtatErreurProps) {
  return (
    <div role="alert" className={className}>
      <EtatVide
        illustration="hors-ligne"
        titre={titre}
        description={description}
        action={onReessayer ? <Bouton variante="contour" onClick={onReessayer}>{t.commun.reessayer}</Bouton> : undefined}
      />
    </div>
  )
}

type AccesRefuseProps = {
  description: ReactNode
  action?: ReactNode
  className?: string
}

export function AccesRefuse({ description, action, className }: AccesRefuseProps) {
  return <EtatVide illustration="acces-refuse" titre={t.etats.accesRefuseTitre} description={description} action={action} className={className} />
}

export type TonAlerte = 'info' | 'succes' | 'alerte' | 'danger' | 'ia'

const alertes: Record<TonAlerte, { classe: string; icone: ReactNode }> = {
  info: { classe: 'border-bordure-info bg-fond-info', icone: <Info aria-hidden="true" className="size-5 text-info" /> },
  succes: { classe: 'border-bordure-succes bg-fond-succes', icone: <CircleCheck aria-hidden="true" className="size-5 text-succes" /> },
  alerte: { classe: 'border-bordure-alerte bg-fond-alerte', icone: <TriangleAlert aria-hidden="true" className="size-5 text-alerte" /> },
  danger: { classe: 'border-bordure-danger bg-fond-danger', icone: <CircleAlert aria-hidden="true" className="size-5 text-danger" /> },
  ia: { classe: 'border-bordure-ia bg-fond-ia', icone: <Sparkles aria-hidden="true" className="size-5 text-ia" /> },
}

type AlerteProps = {
  ton?: TonAlerte
  titre?: ReactNode
  children?: ReactNode
  action?: ReactNode
  /** Annonce immédiate (role="alert") pour une erreur qui vient d'arriver. */
  urgente?: boolean
  className?: string
}

/** Encadré d'information dans le flux de la page (pas un toast). */
export function Alerte({ ton = 'info', titre, children, action, urgente = false, className }: AlerteProps) {
  return (
    <div role={urgente ? 'alert' : undefined} className={cx('flex items-start gap-3 rounded-moyen border px-4 py-3', alertes[ton].classe, className)}>
      <span className="mt-0.5 shrink-0">{alertes[ton].icone}</span>
      <div className="flex min-w-0 flex-1 flex-col gap-1 text-texte">
        {titre ? <p className="m-0 font-medium text-titre">{titre}</p> : null}
        {children ? <div className="text-petit">{children}</div> : null}
      </div>
      {action ? <div className="shrink-0 self-center">{action}</div> : null}
    </div>
  )
}

/** Ce qui manque et pourquoi, quand une partie de l'écran n'a pas pu être chargée. */
export function AvisDonneesPartielles({ children, className }: { children?: ReactNode; className?: string }) {
  return (
    <Alerte ton="alerte" titre={t.etats.donneesPartielles} className={className}>
      {children}
    </Alerte>
  )
}

/** Bandeau persistant tant que le navigateur est hors ligne. */
export function BandeauHorsLigne() {
  const [enLigne, setEnLigne] = useState(() => (typeof navigator === 'undefined' ? true : navigator.onLine))

  useEffect(() => {
    const aller = () => setEnLigne(true)
    const couper = () => setEnLigne(false)
    window.addEventListener('online', aller)
    window.addEventListener('offline', couper)
    return () => {
      window.removeEventListener('online', aller)
      window.removeEventListener('offline', couper)
    }
  }, [])

  return (
    <div role="status" aria-live="polite">
      {enLigne ? null : (
        <div className="flex items-center justify-center gap-2 bg-nuit px-4 py-2 text-center text-petit text-sur-couleur">
          <WifiOff aria-hidden="true" className="size-4 shrink-0" />
          {t.etats.horsLigne}
        </div>
      )}
    </div>
  )
}
