export function ilYA(iso: string, maintenant = Date.now()): string {
  const ecartSecondes = Math.round((new Date(iso).getTime() - maintenant) / 1000)
  const format = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' })
  const abs = Math.abs(ecartSecondes)
  if (abs < 60) return format.format(ecartSecondes, 'second')
  const minutes = Math.round(ecartSecondes / 60)
  if (Math.abs(minutes) < 60) return format.format(minutes, 'minute')
  const heures = Math.round(ecartSecondes / 3600)
  if (Math.abs(heures) < 24) return format.format(heures, 'hour')
  return format.format(Math.round(ecartSecondes / 86400), 'day')
}

export function nomPays(code: string): string {
  try {
    return new Intl.DisplayNames(['fr'], { type: 'region' }).of(code) ?? code
  } catch {
    return code
  }
}
