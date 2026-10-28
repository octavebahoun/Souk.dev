// Carnet de commandes : connexion par code SMS (Souk.dev), notes par l'API du backend intégré.
const $ = (id) => document.getElementById(id)
const etat = { numero: '', jeton: sessionStorage.getItem('jeton') }

function dire(message) { $('annonce').textContent = message; $('erreur').textContent = '' }
function erreur(message) { $('erreur').textContent = message }

async function auth(chemin, corps) {
  const reponse = await fetch(`${window.SOUKDEV.auth}/${chemin}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(corps),
  })
  const donnees = await reponse.json().catch(() => ({}))
  if (!reponse.ok) throw new Error(Object.values(donnees.errors ?? {})[0]?.[0] ?? donnees.message ?? 'Erreur')
  return donnees
}

async function donnees(chemin, options = {}) {
  const reponse = await fetch(`/donnees/${chemin}`, {
    ...options,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${etat.jeton}`, Prefer: 'return=representation', ...(options.headers ?? {}) },
  })
  if (reponse.status === 401) { sortir(); throw new Error('Session expirée : reconnectez-vous.') }
  if (!reponse.ok) throw new Error('Le carnet n’a pas répondu.')
  return reponse.json()
}

function afficher(connecte) {
  $('demande').hidden = connecte
  $('verification').hidden = true
  $('carnet').hidden = !connecte
}

async function chargerNotes() {
  const notes = await donnees('notes?order=cree_le.desc')
  $('notes').replaceChildren(...notes.map((note) => {
    const ligne = document.createElement('li')
    ligne.textContent = note.texte
    const quand = document.createElement('time')
    quand.dateTime = note.cree_le
    quand.textContent = new Date(note.cree_le).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })
    ligne.append(quand)
    return ligne
  }))
  if (notes.length === 0) $('notes').innerHTML = '<li>Aucune commande pour le moment.</li>'
}

function sortir() {
  sessionStorage.removeItem('jeton')
  etat.jeton = null
  afficher(false)
}

$('demande').addEventListener('submit', async (evenement) => {
  evenement.preventDefault()
  etat.numero = $('numero').value
  try {
    await auth('code', { canal: 'sms', destinataire: etat.numero })
    $('verification').hidden = false
    $('code').focus()
    dire('Code envoyé par SMS.')
  } catch (probleme) { erreur(probleme.message) }
})

$('verification').addEventListener('submit', async (evenement) => {
  evenement.preventDefault()
  try {
    const session = await auth('session', { canal: 'sms', destinataire: etat.numero, code: $('code').value })
    etat.jeton = session.jeton
    sessionStorage.setItem('jeton', session.jeton)
    afficher(true)
    dire(`Connecté (${session.utilisateur.destinataire}).`)
    await chargerNotes()
  } catch (probleme) { erreur(probleme.message) }
})

$('ajout').addEventListener('submit', async (evenement) => {
  evenement.preventDefault()
  try {
    await donnees('notes', { method: 'POST', body: JSON.stringify({ texte: $('texte').value }) })
    $('texte').value = ''
    dire('Commande ajoutée.')
    await chargerNotes()
  } catch (probleme) { erreur(probleme.message) }
})

$('sortie').addEventListener('click', () => { sortir(); dire('Déconnecté.') })

if (etat.jeton) { afficher(true); chargerNotes().catch((probleme) => erreur(probleme.message)) } else { afficher(false) }
