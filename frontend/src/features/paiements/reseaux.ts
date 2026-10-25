import airtel from '../../assets/reseaux/airtel.webp'
import celtiis from '../../assets/reseaux/celtiis.webp'
import free from '../../assets/reseaux/free.webp'
import moov from '../../assets/reseaux/moov.webp'
import mtn from '../../assets/reseaux/mtn.webp'
import orange from '../../assets/reseaux/orange.webp'
import togocel from '../../assets/reseaux/togocel.webp'
import wave from '../../assets/reseaux/wave.webp'

/**
 * Logo de la marque d'un réseau Mobile Money (codes SasPay), seulement pour désigner le
 * réseau choisi (LICENSES.md). Un réseau sans logo s'affiche par son nom.
 */
const logos: Record<string, string> = {
  mtn_bj: mtn,
  mtn_ci: mtn,
  moov_bj: moov,
  moov_ci: moov,
  moov_tg: moov,
  moov_bf: moov,
  moov_ml: moov,
  celtiis_bj: celtiis,
  orange_ci: orange,
  orange_sn: orange,
  orange_bf: orange,
  orange_ml: orange,
  wave_ci: wave,
  wave_sn: wave,
  freemoney_sn: free,
  togocel: togocel,
  airtel_ne: airtel,
}

export function logoReseau(code: string | null | undefined): string | null {
  return code ? (logos[code] ?? null) : null
}

/** Les moyens de paiement montrés sous le bouton « Payer » (une marque une seule fois). */
export const moyensAffiches = [
  { nom: 'MTN MoMo', logo: mtn },
  { nom: 'Moov Money', logo: moov },
  { nom: 'Orange Money', logo: orange },
  { nom: 'Wave', logo: wave },
  { nom: 'Celtiis Cash', logo: celtiis },
]

export const nomsPays: Record<string, string> = {
  BJ: 'Bénin',
  CI: 'Côte d’Ivoire',
  SN: 'Sénégal',
  TG: 'Togo',
  BF: 'Burkina Faso',
  ML: 'Mali',
  NE: 'Niger',
}
