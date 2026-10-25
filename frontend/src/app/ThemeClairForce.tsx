import { useEffect } from 'react'
import { Outlet } from 'react-router'
import { forcerThemeClair } from '../ui'

/**
 * Écrans d'avant la refonte : thème clair forcé (leurs couleurs codées en dur ne tiennent pas
 * en sombre). Un écran redessiné sort de ce groupe dans le routeur.
 */
export function ThemeClairForce() {
  useEffect(() => forcerThemeClair(), [])
  return <Outlet />
}
