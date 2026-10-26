import axios from 'axios'
import type { components } from '../../shared/types/api'
import { cheminLaravel, clientLocal } from '../../shared/client'
import { preparerCsrf } from '../../app/preparerCsrf'

type Schemas = components['schemas']
export type Facture = Schemas['Facture']
export type StatutFacture = Schemas['StatutFacture']
export type Paiement = Schemas['Paiement']
export type SessionPaiement = Schemas['SessionPaiement']
export type Deploiement = Schemas['Deploiement']
export type Revenus = Schemas['Revenus']
export type Mouvement = Schemas['Mouvement']
export type Reversement = Schemas['Reversement']
export type CoordonneesReversement = Schemas['CoordonneesReversement']
export type CoordonneesReversementSaisie = Schemas['CoordonneesReversementSaisie']
export type ReseauMobileMoney = Schemas['ReseauMobileMoney']
export type Finances = Schemas['Finances']
export type AvoirCreation = Schemas['AvoirCreation']
export type Avoir = Schemas['Avoir']
export type Rapprochement = Schemas['Rapprochement']
export type ReversementAdmin = Schemas['ReversementAdmin']
export type Page<T> = Schemas['Pagination'] & { data: T[] }

async function page<T>(promesse: Promise<{ data: Page<T> }>): Promise<Page<T>> {
  const { data } = await promesse
  if (!data || !Array.isArray(data.data) || !data.meta) throw new Error('Réponse vide')
  return data
}

async function objet<T>(promesse: Promise<{ data: T }>): Promise<T> {
  const { data } = await promesse
  if (data === undefined || data === null || typeof data !== 'object') throw new Error('Réponse vide')
  return data
}

/** Une clé par intention : rejouée (double clic, réseau coupé), la requête ne recommence pas. */
export function nouvelleCle(): string {
  return crypto.randomUUID()
}

/**
 * Message à montrer au client, tiré de la réponse de l'API (ses messages sont déjà rédigés
 * pour lui), avec un repli qui dit quoi faire.
 */
export function messageErreur(erreur: unknown, repli: string): string {
  if (!axios.isAxiosError(erreur)) return repli
  if (!erreur.response) return 'La connexion a été coupée. Vérifiez votre réseau, puis réessayez.'
  const statut = erreur.response.status
  const donnees = erreur.response.data as { message?: unknown; errors?: Record<string, unknown> } | undefined
  if (statut === 401) return 'Votre session a expiré. Connectez-vous pour continuer.'
  if (statut === 419) return 'La page a expiré. Rechargez-la, puis réessayez.'
  // Une limite propre à l'action (ex. 3 analyses par 24 heures) dit elle-même quand réessayer.
  if (statut === 429) {
    return typeof donnees?.message === 'string' && donnees.message !== '' && donnees.message !== 'Limite de débit atteinte.'
      ? donnees.message
      : 'Trop de tentatives en peu de temps. Patientez une minute, puis réessayez.'
  }
  const premiere = donnees?.errors ? Object.values(donnees.errors).flat().find((texte) => typeof texte === 'string') : undefined
  if (typeof premiere === 'string') return premiere
  if (typeof donnees?.message === 'string' && donnees.message !== '' && statut < 500) return donnees.message
  if (statut === 503 && typeof donnees?.message === 'string') return donnees.message
  return repli
}

/** Statut HTTP d'une erreur d'appel (null : réseau coupé ou erreur hors API). */
export function statutHttp(erreur: unknown): number | null {
  return axios.isAxiosError(erreur) ? (erreur.response?.status ?? null) : null
}

/** Erreurs de validation par champ (422), pour les relier aux champs du formulaire. */
export function erreursDeChamps(erreur: unknown): Record<string, string> {
  if (!axios.isAxiosError(erreur) || erreur.response?.status !== 422) return {}
  const erreurs = (erreur.response.data as { errors?: Record<string, unknown> } | undefined)?.errors ?? {}
  return Object.fromEntries(
    Object.entries(erreurs).flatMap(([champ, messages]) => (Array.isArray(messages) && typeof messages[0] === 'string' ? [[champ, messages[0]]] : [])),
  )
}

// --- Client : commande, factures, paiement ------------------------------------------------

export async function commanderCopie(appId: number, variables: Record<string, string>): Promise<Deploiement> {
  await preparerCsrf()
  return objet(clientLocal.post<Deploiement>(cheminLaravel(`/apps/${appId}/deployments`), { variables }))
}

export function lireCopie(id: number) {
  return objet(clientLocal.get<Deploiement>(cheminLaravel(`/deployments/${id}`)))
}

export function listerFactures(params: { statut?: StatutFacture; page?: number }) {
  return page<Facture>(clientLocal.get(cheminLaravel('/factures'), { params }))
}

export function lireFacture(id: number) {
  return objet(clientLocal.get<Facture>(cheminLaravel(`/factures/${id}`)))
}

export async function payerFacture(id: number, cle: string): Promise<SessionPaiement> {
  await preparerCsrf()
  return objet(clientLocal.post<SessionPaiement>(cheminLaravel(`/factures/${id}/paiement`), {}, { headers: { 'Idempotency-Key': cle } }))
}

/** Revérifie le paiement auprès de SasPay (retour du paiement, bouton « Vérifier »). */
export async function verifierFacture(id: number): Promise<Facture> {
  await preparerCsrf()
  return objet(clientLocal.post<Facture>(cheminLaravel(`/factures/${id}/verification`)))
}

/** Ouvre la page de paiement de SasPay pour une facture (même onglet). */
export async function allerPayer(id: number, cle: string): Promise<void> {
  const session = await payerFacture(id, cle)
  window.location.assign(session.checkout_url)
}

// --- Dev : revenus et reversements ----------------------------------------------------------

export function lireRevenus() {
  return objet(clientLocal.get<Revenus>(cheminLaravel('/me/revenus')))
}

export function listerMouvements(numero = 1) {
  return page<Mouvement>(clientLocal.get(cheminLaravel('/me/revenus/mouvements'), { params: { page: numero } }))
}

export function listerReversements(numero = 1) {
  return page<Reversement>(clientLocal.get(cheminLaravel('/me/revenus/reversements'), { params: { page: numero } }))
}

export async function demanderReversement(cle: string): Promise<Reversement> {
  await preparerCsrf()
  return objet(clientLocal.post<Reversement>(cheminLaravel('/me/revenus/reversements'), {}, { headers: { 'Idempotency-Key': cle } }))
}

export async function enregistrerCoordonnees(saisie: CoordonneesReversementSaisie): Promise<CoordonneesReversement> {
  await preparerCsrf()
  return objet(clientLocal.put<CoordonneesReversement>(cheminLaravel('/me/coordonnees-reversement'), saisie))
}

export async function listerReseaux(): Promise<ReseauMobileMoney[]> {
  const { data } = await clientLocal.get<ReseauMobileMoney[]>(cheminLaravel('/reseaux-mobile-money'))
  if (!Array.isArray(data)) throw new Error('Réponse vide')
  return data
}

// --- Admin : vue financière ------------------------------------------------------------------

export function lireFinances(params: { debut?: string; fin?: string }) {
  return objet(clientLocal.get<Finances>(cheminLaravel('/admin/finances'), { params }))
}

export function listerFacturesAdmin(params: { statut?: StatutFacture; page?: number }) {
  return page<Facture>(clientLocal.get(cheminLaravel('/admin/factures'), { params }))
}

export async function creerAvoir(factureId: number, corps: AvoirCreation, cle: string): Promise<Avoir> {
  await preparerCsrf()
  return objet(clientLocal.post<Avoir>(cheminLaravel(`/admin/factures/${factureId}/avoir`), corps, { headers: { 'Idempotency-Key': cle } }))
}

export type TropPercu = Schemas['TropPercu']

export async function rembourserTropPercu(paiementId: number, motif: string, cle: string): Promise<TropPercu> {
  await preparerCsrf()
  return objet(clientLocal.post<TropPercu>(cheminLaravel(`/admin/paiements/${paiementId}/remboursement`), { motif }, { headers: { 'Idempotency-Key': cle } }))
}

export function listerReversementsAdmin(numero = 1) {
  return page<ReversementAdmin>(clientLocal.get(cheminLaravel('/admin/reversements'), { params: { page: numero } }))
}

export function listerRapprochements(numero = 1) {
  return page<Rapprochement>(clientLocal.get(cheminLaravel('/admin/rapprochements'), { params: { page: numero } }))
}
