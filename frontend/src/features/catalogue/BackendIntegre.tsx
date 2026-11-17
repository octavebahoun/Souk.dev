import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { ArrowRight, Database, KeyRound, MessageSquareCode, ShieldCheck } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { Carte, EnTetePage, FilAriane, LienBouton } from '../../ui'

const atouts = [
  { icone: Database, titre: 'Une base PostgreSQL par client', texte: 'Chaque copie reçoit sa propre base, vide, avec vos tables. Les données d’un client ne croisent jamais celles d’un autre.' },
  { icone: MessageSquareCode, titre: 'Une API prête à l’emploi', texte: 'Vos tables sont lisibles et modifiables en HTTP (PostgREST), sans écrire une ligne de serveur.' },
  { icone: KeyRound, titre: 'La connexion des utilisateurs', texte: 'Un code à 6 chiffres par SMS ou par e-mail, puis un jeton : vos utilisateurs n’ont pas de mot de passe à retenir.' },
  { icone: ShieldCheck, titre: 'Chacun ne voit que ses lignes', texte: 'Les règles de la base (RLS) cloisonnent les données par utilisateur. Sauvegarde chaque jour et export compris.' },
]

function Code({ titre, children }: { titre: string; children: string }) {
  return (
    <figure className="m-0 flex flex-col gap-1.5">
      <figcaption className="text-petit font-medium text-titre">{titre}</figcaption>
      <pre tabIndex={0} aria-label={titre} className="m-0 overflow-x-auto rounded-moyen border border-bordure bg-nuit p-4 font-mono text-petit leading-relaxed text-sur-couleur">
        {children}
      </pre>
    </figure>
  )
}

function Etape({ numero, titre, children }: { numero: number; titre: string; children: ReactNode }) {
  return (
    <section className="flex flex-col gap-3">
      <h2 className="m-0 flex items-center gap-3 text-titre-3 text-titre">
        <span aria-hidden="true" className="chiffres grid size-8 shrink-0 place-items-center rounded-pastille bg-primaire text-corps text-sur-primaire">
          {numero}
        </span>
        {titre}
      </h2>
      {children}
    </section>
  )
}

/**
 * Le backend intégré, expliqué aux devs : une base, une API et la connexion des utilisateurs,
 * fournies à chaque copie par Souk.dev (comme Supabase, en plus simple). Aucun serveur à écrire.
 */
export function BackendIntegre() {
  useTitrePage('Backend intégré', {
    description: 'Publiez une appli sans écrire de serveur : Souk.dev fournit à chaque copie sa base PostgreSQL, son API et la connexion de ses utilisateurs par code SMS ou e-mail.',
  })

  return (
    <div className="mx-auto flex w-full max-w-prose flex-col gap-8 px-4 py-8 tablette:px-6">
      <FilAriane miettes={[{ libelle: 'Publier une appli', vers: '/publier' }, { libelle: 'Backend intégré' }]} />
      <EnTetePage
        surTitre="Pour les devs"
        titre="Backend intégré : votre appli sans serveur à écrire"
        description="Vous savez faire un front, pas forcément une API ? Déclarez le backend intégré : chaque copie de votre appli reçoit sa base, son API et la connexion de ses utilisateurs."
      />

      <ul className="m-0 grid list-none gap-3 p-0 tablette:grid-cols-2">
        {atouts.map(({ icone: Icone, titre, texte }) => (
          <li key={titre}>
            <Carte className="flex h-full flex-col gap-2">
              <span aria-hidden="true" className="grid size-9 place-items-center rounded-moyen bg-surface-accent text-sur-primaire-doux">
                <Icone className="size-4.5" />
              </span>
              <h3 className="m-0 text-corps font-semibold text-titre">{titre}</h3>
              <p className="m-0 text-petit text-texte">{texte}</p>
            </Carte>
          </li>
        ))}
      </ul>

      <Etape numero={1} titre="Déclarez-le dans soukdev.json">
        <p className="m-0 text-corps text-texte">
          À la racine du dépôt, <code>"backend": "integre"</code> et le dossier de vos migrations SQL. Pas de base dans votre docker-compose.yml : la plateforme
          l’ajoute.
        </p>
        <Code titre="soukdev.json">{`{
  "version": 1,
  "service_web": { "nom": "web", "port": 80 },
  "backend": "integre",
  "migrations": "db",
  "variables": []
}`}</Code>
      </Etape>

      <Etape numero={2} titre="Écrivez vos tables">
        <p className="m-0 text-corps text-texte">
          Des fichiers <code>.sql</code> dans <code>db/</code>, appliqués dans l’ordre de leur nom à chaque nouvelle copie, puis seulement les nouveaux à chaque mise à
          jour. <code>soukdev.utilisateur_id()</code> donne l’utilisateur connecté.
        </p>
        <Code titre="db/001_notes.sql">{`CREATE TABLE notes (
    id bigserial PRIMARY KEY,
    utilisateur uuid NOT NULL DEFAULT soukdev.utilisateur_id(),
    texte text NOT NULL
);

-- Chacun ne voit et n'écrit que ses notes.
ALTER TABLE notes ENABLE ROW LEVEL SECURITY;
CREATE POLICY lecture ON notes FOR SELECT TO soukdev_utilisateur
    USING (utilisateur = soukdev.utilisateur_id());
CREATE POLICY ecriture ON notes FOR INSERT TO soukdev_utilisateur
    WITH CHECK (utilisateur = soukdev.utilisateur_id());`}</Code>
      </Etape>

      <Etape numero={3} titre="Connectez vos utilisateurs, puis lisez vos données">
        <p className="m-0 text-corps text-texte">
          Votre appli reçoit <code>SOUKDEV_AUTH_URL</code> (connexion) et <code>SOUKDEV_URL</code> (l’API de la base). Un code part par SMS ou par e-mail, il
          s’échange contre un jeton valable un jour.
        </p>
        <Code titre="Dans votre appli (JavaScript)">{`// 1. Envoyer un code (SMS ou e-mail)
await fetch(\`\${SOUKDEV_AUTH_URL}/code\`, { method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ canal: 'sms', destinataire: '+22997000000' }) })

// 2. Échanger le code contre un jeton
const { jeton } = await (await fetch(\`\${SOUKDEV_AUTH_URL}/session\`, { method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ canal: 'sms', destinataire: '+22997000000', code: '123456' }) })).json()

// 3. Lire ses notes, et seulement les siennes
const notes = await (await fetch('/donnees/notes', {
  headers: { Authorization: \`Bearer \${jeton}\` } })).json()`}</Code>
      </Etape>

      <Carte className="flex flex-col gap-3 bg-surface-accent">
        <h2 className="m-0 text-titre-3 text-titre">Un exemple qui tourne : Carnet de commandes</h2>
        <p className="m-0 text-corps text-texte">
          L’appli Carnet du Store utilise le backend intégré : chaque client se connecte par un code SMS et ne voit que ses commandes. Commandez-en une copie, ou
          lisez son code pour partir d’un exemple qui marche.
        </p>
        <div className="flex flex-wrap gap-2">
          <LienBouton to="/store?q=Carnet" iconeFin={<ArrowRight aria-hidden="true" className="size-4" />}>
            Voir Carnet dans le Store
          </LienBouton>
          <LienBouton to="/publier" variante="contour">
            Publier mon appli
          </LienBouton>
        </div>
      </Carte>

      <p className="m-0 text-petit text-texte-doux">
        Une question ? Posez-la dans le canal <Link to="/echange?canal=entraide-generale">#entraide-generale</Link> de l’Échange.
      </p>
    </div>
  )
}
