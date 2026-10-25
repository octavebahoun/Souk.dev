<?php

namespace Database\Seeders;

use App\Comptes\ProfilCalcule;
use App\Models\Abonnement;
use App\Models\Appli;
use App\Models\Avoir;
use App\Models\Canal;
use App\Models\CoordonneesReversement;
use App\Models\Correctif;
use App\Models\Deploiement;
use App\Models\Discussion;
use App\Models\Ecriture;
use App\Models\Evenement;
use App\Models\Facture;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Paiement;
use App\Models\Reversement;
use App\Models\User;
use App\Paiement\Facturation;
use App\Paiement\GrandLivre;
use App\Paiement\NumeroMobileMoney;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Jeu de démonstration, identifié comme tel : comptes marqués est_demo (identifiants
 * « soukdemo-… », adresses @demo.souk.dev), lancé à la demande, jamais par défaut :
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Relançable : il supprime d'abord ses propres comptes (et tout ce qu'ils ont créé, factures
 * et écritures du grand livre comprises).
 * Applis vraiment déployables : octavebahoun/marchilearnia (site statique servi par nginx) et,
 * en développement, PharmaStock (appli cobaye de database/seeders/demo/depots, clonée depuis un
 * miroir local : php artisan demo:miroirs). Les autres dépôts n'existent pas.
 * Facturation de démo : paiements simulés (passerelle « simulee »), jamais d'argent réel.
 * Images : scripts/visuels/ (Nano Banana Pro) et une capture du vrai site de Marchi Learnia.
 */
class DemoSeeder extends Seeder
{
    private const DOMAINE = '@demo.souk.dev';

    /** @var array<string, User> */
    private array $comptes = [];

    /** @var array<string, Appli> */
    private array $applis = [];

    /** @var array<string, Canal> */
    private array $canaux = [];

    public function run(ProfilCalcule $profils): void
    {
        DB::transaction(function (): void {
            $this->effacer();
            $this->creerComptes();
            $this->creerCanaux();
            $this->creerApplis();
            $this->creerDiscussions();
            $this->creerEvenements();
            $this->creerMissions();
            $this->creerFacturation();
        });

        if (app()->environment('local')) {
            Artisan::call('demo:miroirs');
        }

        foreach ($this->comptes as $compte) {
            $profils->rafraichir($compte);
        }

        $this->command?->info('Jeu de démo prêt : '.count($this->comptes).' comptes, '.count($this->applis).' applis.');
        $this->command?->line('Connexion de démo (DEMO_CONNEXION=true) : POST /auth/demo {"compte": "soukdemo-awa"}');
    }

    private function effacer(): void
    {
        $this->effacerFacturation();

        // Les comptes de démo emportent leurs applis, copies, discussions, missions et événements.
        User::query()->where('est_demo', true)->get()->each->delete();

        Canal::query()
            ->whereIn('slug', ['laravel', 'mobile-money', 'react', 'devops', 'laravel-benin', 'dakar-tech', 'entraide-generale'])
            ->whereDoesntHave('discussions')
            ->delete();
    }

    private function creerComptes(): void
    {
        $devs = [
            'awa' => ['Awa Dossou', 'BJ', 'Développeuse Laravel à Cotonou. Paiements Mobile Money, API et applis de gestion pour les pharmacies.', ['laravel', 'vue', 'mysql'], ['backend'], true],
            'kofi' => ['Kofi Mensah', 'TG', 'Frontend à Lomé. React, Node et interfaces rapides sur les réseaux mobiles.', ['react', 'node', 'postgresql'], ['frontend'], true],
            'mariam' => ['Mariam Sow', 'SN', 'Ingénieure data à Dakar. Django, PostgreSQL et tableaux de bord pour la santé.', ['django', 'postgresql', 'fastapi'], ['data', 'backend'], true],
            'yao' => ['Yao Kouassi', 'CI', 'Développeur mobile et web à Abidjan. Applis scolaires et intégrations Wave.', ['react', 'node', 'mongodb'], ['mobile', 'frontend'], false],
            'chinedu' => ['Chinedu Okafor', 'NG', 'DevOps à Lagos. Go, Docker et déploiements fiables pour les petites équipes.', ['go', 'postgresql', 'redis'], ['devops', 'backend'], true],
            'fatou' => ['Fatou Ndiaye', 'SN', 'Sécurité applicative et Next.js. J’aide les associations à se lancer en ligne.', ['nextjs', 'postgresql', 'node'], ['securite', 'frontend'], true],
        ];

        foreach ($devs as $cle => [$nom, $pays, $bio, $competences, $specialites, $disponible]) {
            $this->comptes[$cle] = $this->compte($cle, [
                'name' => $nom,
                'profil' => 'dev',
                'pays' => $pays,
                'bio' => $bio,
                'competences' => $competences,
                'specialites' => $specialites,
                'disponible' => $disponible,
                'github_lie' => true,
                'avatar_url' => $this->avatar($cle),
            ]);
        }

        $this->comptes['admin'] = $this->compte('admin', [
            'name' => 'Équipe Souk.dev',
            'profil' => 'dev',
            'github_lie' => true,
            'est_admin' => true,
        ]);

        $this->comptes['pharmacie'] = $this->compte('pharmacie', [
            'name' => 'Pharmacie du Port',
            'profil' => 'client',
            'pays' => 'BJ',
            'github_lie' => false,
        ]);

        $this->comptes['association'] = $this->compte('association', [
            'name' => 'Association Lumière',
            'profil' => 'client',
            'pays' => 'TG',
            'github_lie' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $champs
     */
    private function compte(string $cle, array $champs): User
    {
        $compte = new User;
        $compte->forceFill([
            ...$champs,
            'email' => $cle.self::DOMAINE,
            'username' => 'soukdemo-'.$cle,
            'est_demo' => true,
            'email_verified_at' => now(),
        ])->save();

        return $compte;
    }

    private function creerCanaux(): void
    {
        $canaux = [
            'laravel' => ['Laravel', 'Tout sur Laravel, des files d’attente aux tests.', null],
            'mobile-money' => ['Mobile Money', 'MTN MoMo, Moov, Orange Money, Wave : intégrations et pièges.', null],
            'react' => ['React', 'Composants, performances et rendu sur réseau lent.', null],
            'devops' => ['DevOps', 'Docker, déploiement, supervision.', null],
            'laravel-benin' => ['Laravel Bénin', 'La communauté Laravel du Bénin.', 'BJ'],
            'dakar-tech' => ['Dakar Tech', 'Les devs de Dakar et du Sénégal.', 'SN'],
            'entraide-generale' => ['Entraide générale', 'Pour tout le reste : projets, carrière, conseils.', null],
        ];

        foreach ($canaux as $slug => [$nom, $description, $pays]) {
            $this->canaux[$slug] = Canal::query()->firstOrCreate(['slug' => $slug], [
                'nom' => $nom,
                'description' => $description,
                'pays' => $pays,
            ]);
        }
    }

    private function creerApplis(): void
    {
        $applis = [
            'marchi-learnia' => ['mariam', 'Learnia — suivi de formation', 'Suivez une formation vidéo Python et Machine Learning : chaque vidéo se coche une fois terminée, la progression reste affichée. Site statique léger, idéal pour une école ou un club de code.', 'education', 0, 'mensuel', 'petite', 'https://marchinelearnia.vercel.app', 'https://github.com/octavebahoun/marchilearnia', ['autre'], 'verifie', 42],
            'pharmastock' => ['awa', 'PharmaStock', 'Gestion de stock pour pharmacie : lots, dates de péremption, ordonnances à préparer et ventes du jour en FCFA. Alerte avant chaque rupture.', 'sante', 15000, 'mensuel', 'moyenne', 'https://pharmastock-demo.souk.dev', 'https://github.com/soukdemo/pharmastock', ['laravel', 'vue', 'mysql'], 'verifie', 610],
            'caisse-resto' => ['kofi', 'Caisse Resto', 'Caisse tactile pour maquis et restaurants : tables, tickets, encaissement en espèces ou Mobile Money, rapport de fin de journée.', 'restauration', 9000, 'mensuel', 'petite', 'https://caisse-resto-demo.souk.dev', 'https://github.com/soukdemo/caisse-resto', ['react', 'node', 'postgresql'], 'problemes', 380],
            'cotis-asso' => ['fatou', 'Cotis’Asso', 'Cotisations des membres d’une association : qui est à jour, relances par SMS, reçus. Gratuit pour les associations.', 'association', 0, 'mensuel', 'petite', 'https://cotis-asso-demo.souk.dev', 'https://github.com/soukdemo/cotis-asso', ['nextjs', 'postgresql'], 'verifie', 290],
            'ecole-notes' => ['yao', 'École+ Notes', 'Notes, moyennes et bulletins trimestriels pour collèges et lycées. Les parents reçoivent le bulletin par e-mail.', 'education', 75000, 'unique', 'moyenne', 'https://ecole-notes-demo.souk.dev', 'https://github.com/soukdemo/ecole-notes', ['node', 'react', 'mongodb'], 'en_cours', null],
            'momo-pay-kit' => ['awa', 'MoMo Pay Kit', 'Console d’encaissement pour développeurs : transactions MTN, Moov et Wave, webhooks rejoués, clés API cachées.', 'finance', 0, 'mensuel', 'petite', 'https://momo-pay-kit-demo.souk.dev', 'https://github.com/soukdemo/momo-pay-kit', ['laravel'], 'verifie', 210],
            'stock-boutique' => ['chinedu', 'Stock Boutique', 'Inventaire pour boutique de quartier : produits, quantités, alertes de stock bas et ventes de la semaine.', 'commerce', 12000, 'mensuel', 'moyenne', 'https://stock-boutique-demo.souk.dev', 'https://github.com/soukdemo/stock-boutique', ['go', 'postgresql', 'redis'], 'verifie', 540],
            'clinique-rdv' => ['mariam', 'Clinique RDV', 'Prise de rendez-vous pour cliniques : agenda par médecin, rappels par SMS, fiche patient.', 'sante', 20000, 'mensuel', 'grande', 'https://clinique-rdv-demo.souk.dev', 'https://github.com/soukdemo/clinique-rdv', ['django', 'postgresql'], 'problemes', 1320],
        ];

        foreach ($applis as $cle => [$auteur, $nom, $description, $categorie, $prix, $typePrix, $taille, $demo, $depot, $stack, $securite, $memoire]) {
            $appli = Appli::query()->create([
                'user_id' => $this->comptes[$auteur]->id,
                'nom' => $nom,
                'description' => $description,
                'categorie' => $categorie,
                'prix' => $prix,
                'type_prix' => $typePrix,
                'taille' => $taille,
                'demo_url' => $demo,
                'depot_url' => $depot,
                'stack' => $stack,
                'backend' => 'propre',
                'variables_client' => [],
            ]);

            $appli->forceFill([
                'captures' => [['url' => $this->capture($appli, $cle)]],
                'securite_statut' => $securite,
                'securite_analyse_le' => $securite === 'en_cours' ? null : now()->subDays(2),
                'securite_problemes' => $securite === 'problemes' ? $this->problemes() : [],
                'mesure_statut' => $memoire === null ? 'en_cours' : 'terminee',
                'memoire_max_mo' => $memoire,
                'taille_recommandee' => $memoire === null ? null : ($memoire < 450 ? 'petite' : ($memoire < 900 ? 'moyenne' : 'grande')),
                'mesure_le' => $memoire === null ? null : now()->subDays(2),
                'vues' => random_int(40, 900),
                'created_at' => now()->subDays(random_int(3, 40)),
            ])->save();

            $this->applis[$cle] = $appli;
        }
    }

    /**
     * @return list<array{identifiant: string, paquet: string, version: string, gravite: string, version_corrigee: string|null}>
     */
    private function problemes(): array
    {
        return [
            ['identifiant' => 'CVE-2024-4068', 'paquet' => 'braces', 'version' => '3.0.2', 'gravite' => 'haute', 'version_corrigee' => '3.0.3'],
            ['identifiant' => 'CVE-2024-28849', 'paquet' => 'follow-redirects', 'version' => '1.15.4', 'gravite' => 'moyenne', 'version_corrigee' => '1.15.6'],
        ];
    }

    private function creerDiscussions(): void
    {
        $momo = $this->discussion('awa', 'mobile-money', null, 'Le callback MTN MoMo renvoie 500 en production', "Depuis la mise en production, le callback de MTN MoMo répond 500 alors que tout marche en local.\n\nLe paiement est bien débité côté client, mais la commande reste « en attente ».", ['bug'], [
            'erreur_obtenue' => 'Le callback renvoie 500 : « Call to a member function update() on null ».',
            'comportement_attendu' => 'La commande passe en « payée » dès que MTN confirme le paiement.',
            'techno' => 'laravel',
            'code' => "public function callback(Request \$request)\n{\n    \$commande = Commande::firstWhere('reference', \$request->externalId);\n    \$commande->update(['statut' => 'payee']);\n}",
            'difficulte' => 'moyen',
        ], 'https://github.com/soukdemo/pharmastock');

        $this->messages($momo, [
            ['kofi', 'MTN envoie `externalId` dans le corps JSON, pas en paramètre : avec `$request->externalId` tu lis peut-être `null`. Tu peux logger la requête complète ?'],
            ['awa', 'Bien vu, le corps arrive en `application/json` et la référence est dans `financialTransactionId`… Je teste.'],
            ['chinedu', 'Pense aussi à rendre le callback idempotent : MTN le rejoue s’il n’a pas de 200 en moins de 10 secondes.'],
        ]);

        $correctifKofi = $this->correctif($momo, 'kofi', 'https://github.com/soukdemo/pharmastock/tree/correctif-callback-momo', 'Lit la référence depuis le corps JSON, renvoie 200 même si la commande est déjà payée.');
        $this->correctif($momo, 'chinedu', 'https://github.com/soukdemo/pharmastock/tree/idempotence-callback', 'Ajoute un verrou sur la commande pour les callbacks rejoués.');
        $correctifKofi->forceFill(['accepte' => true, 'accepte_le' => now()->subDays(1)])->save();
        $momo->forceFill(['resolue' => true])->save();

        $wave = $this->discussion('yao', 'mobile-money', null, 'Wave CI : la signature du webhook ne correspond jamais', 'Je recalcule le HMAC du webhook Wave mais la signature diffère toujours. J’ai essayé avec le corps décodé puis ré-encodé.', ['bug'], [
            'erreur_obtenue' => 'hash_equals renvoie toujours false.',
            'comportement_attendu' => 'La signature calculée correspond à l’en-tête Wave-Signature.',
            'techno' => 'node',
            'code' => "const signature = crypto.createHmac('sha256', secret).update(JSON.stringify(req.body)).digest('hex')",
            'difficulte' => 'difficile',
        ]);
        $this->messages($wave, [
            ['fatou', 'Il faut signer le corps **brut**, jamais `JSON.stringify(req.body)` : l’ordre des clés et les espaces changent. Avec Express, garde le buffer via `express.raw()`.'],
        ]);
        $this->correctif($wave, 'fatou', 'https://github.com/soukdemo/ecole-notes/tree/webhook-wave-brut', 'Vérifie la signature sur le corps brut et compare en temps constant.');

        $this->discussion('mariam', 'laravel-benin', null, 'Comment structurer les rôles dans une appli de pharmacie ?', 'Pharmacien, préparateur, caissier : vous utilisez des Policies Laravel, un paquet de rôles, ou une simple colonne ?', ['question']);
        $this->discussion('chinedu', 'devops', null, 'Tuto : déployer une appli Laravel sur Souk.dev avec soukdev.json', "Voici le `soukdev.json` minimal qui marche pour une appli Laravel avec sa propre base :\n\n```json\n{\n  \"version\": 1,\n  \"service_web\": { \"nom\": \"web\", \"port\": 8000 },\n  \"backend\": \"propre\",\n  \"variables\": [{ \"nom\": \"DB_PASSWORD\", \"source\": \"plateforme\" }]\n}\n```", ['tuto']);
        $this->discussion('fatou', 'entraide-generale', null, 'Projet : une appli de tontine digitale, qui est partant ?', 'Je cherche deux devs (un backend, un mobile) pour une tontine avec Mobile Money et des rappels par SMS.', ['projet']);
        $this->discussion('kofi', null, $this->applis['marchi-learnia'], 'Ajouter l’export PDF des progrès', 'Une école voudrait imprimer la progression de chaque élève. Un export PDF serait possible ?', ['question']);
    }

    /**
     * @param  list<string>  $etiquettes
     * @param  array<string, mixed>|null  $bug
     */
    private function discussion(string $auteur, ?string $canal, ?Appli $appli, string $titre, string $texte, array $etiquettes, ?array $bug = null, ?string $depot = null): Discussion
    {
        return Discussion::query()->create([
            'user_id' => $this->comptes[$auteur]->id,
            'canal_id' => $canal !== null ? $this->canaux[$canal]->id : null,
            'app_id' => $appli?->id,
            'titre' => $titre,
            'texte' => $texte,
            'depot_url' => $depot,
            'etiquettes' => $etiquettes,
            'bug' => $bug,
            'resolue' => false,
        ]);
    }

    /**
     * @param  list<array{string, string}>  $messages
     */
    private function messages(Discussion $discussion, array $messages): void
    {
        foreach ($messages as [$auteur, $texte]) {
            Message::query()->create([
                'discussion_id' => $discussion->id,
                'user_id' => $this->comptes[$auteur]->id,
                'texte' => $texte,
            ]);
        }
    }

    private function correctif(Discussion $discussion, string $auteur, string $depot, string $description): Correctif
    {
        return Correctif::query()->create([
            'discussion_id' => $discussion->id,
            'user_id' => $this->comptes[$auteur]->id,
            'depot_url' => $depot,
            'description' => $description,
            'accepte' => false,
        ]);
    }

    private function creerEvenements(): void
    {
        $meetup = Evenement::query()->create([
            'user_id' => $this->comptes['awa']->id,
            'titre' => 'Meetup Laravel Cotonou #4',
            'description' => 'Retours d’expérience sur les paiements Mobile Money et les files d’attente. Pizza et jus de bissap offerts.',
            'date' => now()->subDays(10)->setTime(18, 30),
            'type' => 'presentiel',
            'lieu' => 'Sèmè City, Cotonou',
        ]);
        foreach (['kofi', 'mariam', 'chinedu'] as $cle) {
            $meetup->inscriptions()->create(['user_id' => $this->comptes[$cle]->id]);
        }
        $meetup->avis()->create(['user_id' => $this->comptes['kofi']->id, 'note' => 5, 'commentaire' => 'Très concret, on repart avec du code qui marche.']);
        $meetup->avis()->create(['user_id' => $this->comptes['mariam']->id, 'note' => 4, 'commentaire' => 'Super ambiance, un peu court.']);

        Evenement::query()->create([
            'user_id' => $this->comptes['chinedu']->id,
            'titre' => 'Atelier en ligne : encaisser par Mobile Money avec SasPay',
            'description' => 'Une heure pour intégrer un paiement Mobile Money de bout en bout : session de paiement, webhook signé, vérification.',
            'date' => now()->addDays(12)->setTime(19, 0),
            'type' => 'en_ligne',
            'lien' => 'https://meet.example.com/souk-atelier-saspay',
        ]);

        Evenement::query()->create([
            'user_id' => $this->comptes['fatou']->id,
            'titre' => 'Hackathon Dakar Tech : applis pour les associations',
            'description' => 'Un week-end pour construire et publier sur Souk.dev des applis utiles aux associations sénégalaises.',
            'date' => now()->addDays(30)->setTime(9, 0),
            'type' => 'presentiel',
            'lieu' => 'Jokkolabs, Dakar',
        ]);
    }

    private function creerMissions(): void
    {
        $mission = Mission::query()->create([
            'client_id' => $this->comptes['pharmacie']->id,
            'auteur_id' => $this->comptes['awa']->id,
            'app_id' => $this->applis['pharmastock']->id,
            'message' => 'Nous aimerions ajouter la gestion des ordonnances scannées et la prise de rendez-vous pour la vaccination.',
            'budget' => 250000,
            'delai' => now()->addMonth()->toDateString(),
            'statut' => 'en_cours',
        ]);
        foreach ([['pharmacie', 'Bonjour Awa, voici la liste des écrans souhaités.'], ['awa', 'Merci ! Je vous propose une première version en trois semaines.'], ['pharmacie', 'Parfait, on valide.']] as [$auteur, $texte]) {
            $mission->messages()->create(['auteur_id' => $this->comptes[$auteur]->id, 'texte' => $texte]);
        }

        Mission::query()->create([
            'client_id' => $this->comptes['association']->id,
            'auteur_id' => $this->comptes['fatou']->id,
            'message' => 'Un site de collecte de dons avec paiement Mobile Money et reçus automatiques.',
            'budget' => 180000,
            'statut' => 'terminee',
            'note' => 5,
            'note_commentaire' => 'Travail soigné et livré à temps. Merci Fatou !',
            'terminee_le' => now()->subDays(6),
        ]);
    }

    /**
     * Facturation et grand livre des comptes de démo. Le grand livre refuse toute suppression,
     * sauf cette purge (PostgreSQL : SET LOCAL soukdev.purge = 'demo', dans la transaction).
     */
    private function effacerFacturation(): void
    {
        $comptes = User::query()->where('est_demo', true)->pluck('id');

        if ($comptes->isEmpty()) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SET LOCAL soukdev.purge = 'demo'");
        }

        $abonnements = Abonnement::query()->whereIn('client_id', $comptes)->orWhereIn('dev_id', $comptes)->pluck('id');
        $factures = Facture::query()->whereIn('abonnement_id', $abonnements)->pluck('id');
        $reversements = Reversement::query()->whereIn('dev_id', $comptes)->pluck('id');
        $ecritures = Ecriture::query()->toBase()->where(fn ($requete) => $requete
            ->whereIn('facture_id', $factures)
            ->orWhereIn('reversement_id', $reversements)
            ->orWhereIn('dev_id', $comptes));

        // Contrepassations d'abord : elles pointent vers les écritures qu'elles annulent.
        (clone $ecritures)->whereNotNull('contrepasse_id')->delete();
        $ecritures->delete();
        Avoir::query()->whereIn('facture_id', $factures)->delete();
        Paiement::query()->whereIn('facture_id', $factures)->delete();
        Facture::query()->whereIn('id', $factures)->delete();
        Abonnement::query()->whereIn('id', $abonnements)->delete();
        Reversement::query()->whereIn('id', $reversements)->delete();
    }

    /**
     * La Pharmacie du Port a eu une copie de PharmaStock (deux mois payés) et en attend une
     * nouvelle ; l'Association Lumière a hébergé Cotis'Asso. Awa a reçu un reversement et garde
     * un solde, sur un numéro de démo confirmé.
     */
    private function creerFacturation(): void
    {
        $facturation = app(Facturation::class);
        $grandLivre = app(GrandLivre::class);
        $debut = now()->startOfMonth()->subMonths(2)->addDays(4);

        $ancienne = $this->copieClient('pharmacie', 'pharmastock', 'arrete', $debut);
        foreach ([0, 1] as $mois) {
            $this->facturePayee($ancienne, $debut->copy()->addMonthsNoOverflow($mois), $facturation, $grandLivre);
        }
        $ancienne->forceFill(['statut' => 'resilie', 'resilie_le' => $debut->copy()->addMonthsNoOverflow(2)])->save();

        $lumiere = $this->copieClient('association', 'cotis-asso', 'arrete', $debut);
        $this->facturePayee($lumiere, $debut, $facturation, $grandLivre);
        $lumiere->forceFill(['statut' => 'resilie', 'resilie_le' => $debut->copy()->addMonth()])->save();

        // Nouvelle commande, pas encore payée : la copie attend le paiement.
        $facturation->commander($this->applis['pharmastock'], $this->comptes['pharmacie'], []);

        // Un numéro qui finit par 0000 simule un refus du réseau (SimulateurSasPay) : pas celui-ci.
        $numero = NumeroMobileMoney::normaliser('BJ', '0197123456');
        CoordonneesReversement::query()->create([
            'user_id' => $this->comptes['awa']->id,
            'pays' => 'BJ',
            'reseau' => 'mtn_bj',
            'numero' => $numero,
            'numero_masque' => NumeroMobileMoney::masquer('BJ', $numero),
        ])->forceFill(['confirme_le' => $debut])->save();

        Carbon::setTestNow($debut->copy()->addMonth()->startOfMonth()->addHours(8));
        $reversement = Reversement::query()->create([
            'dev_id' => $this->comptes['awa']->id,
            'montant' => 15000,
            'passerelle' => 'simulee',
            'cle_idempotence' => (string) Str::uuid(),
            'pays' => 'BJ',
            'reseau' => 'mtn_bj',
            'numero_masque' => NumeroMobileMoney::masquer('BJ', $numero),
            'beneficiaire' => ['numero' => $numero, 'email' => $this->comptes['awa']->email, 'nom' => $this->comptes['awa']->name],
            'declenchement' => 'mensuel',
            'statut' => 'reussi',
        ]);
        $reversement->forceFill(['frais' => 380, 'verifie_le' => now()])->save();
        $grandLivre->reserverReversement($reversement);
        $grandLivre->fraisReversement($reversement, 380);
        Carbon::setTestNow();
    }

    private function copieClient(string $client, string $appli, string $etat, Carbon $debut): Abonnement
    {
        $copie = Deploiement::query()->create([
            'user_id' => $this->comptes[$client]->id,
            'appli_id' => $this->applis[$appli]->id,
            'type' => 'client',
            'taille' => $this->applis[$appli]->taille,
            'etat' => $etat,
            'variables' => [],
        ]);
        $copie->forceFill(['created_at' => $debut, 'updated_at' => $debut])->save();

        return Abonnement::query()->create([
            'deploiement_id' => $copie->id,
            'client_id' => $copie->user_id,
            'dev_id' => $this->applis[$appli]->user_id,
            'appli_id' => $copie->appli_id,
            'formule' => $copie->taille,
            'montant_hebergement' => app(Facturation::class)->prixHebergement($copie->taille),
            'prix_appli' => (int) $this->applis[$appli]->prix,
            'type_prix' => $this->applis[$appli]->type_prix,
            'statut' => 'actif',
            'periode_debut' => $debut->toDateString(),
            'prochaine_echeance' => $debut->copy()->addMonthNoOverflow()->toDateString(),
        ]);
    }

    /** Facture d'un mois, payée par le simulateur, passée au grand livre à sa date. */
    private function facturePayee(Abonnement $abonnement, Carbon $debut, Facturation $facturation, GrandLivre $grandLivre): void
    {
        Carbon::setTestNow($debut->copy()->subDays(3)->setTime(6, 0));
        $facture = $facturation->emettre($abonnement, $debut);

        Carbon::setTestNow($debut->copy()->subDays(1)->setTime(10, 12));
        $paiement = Paiement::query()->create([
            'facture_id' => $facture->id,
            'client_id' => $facture->client_id,
            'passerelle' => 'simulee',
            'session_id' => (string) Str::uuid(),
            'montant' => $facture->montant_total,
            'statut' => 'reussi',
        ]);
        $paiement->forceFill([
            'transaction_id' => (string) Str::uuid(),
            'reference' => 'DEMO-'.$facture->numero,
            'frais_client' => (int) round($facture->montant_total * 0.035),
            'montant_debite' => $facture->montant_total + (int) round($facture->montant_total * 0.035),
            'reseau' => 'mtn_bj',
            'verifie_le' => now(),
        ])->save();
        $facture->forceFill(['statut' => 'payee', 'payee_le' => now(), 'rappel_envoye_le' => $debut->copy()->subDays(3)])->save();
        $grandLivre->encaisser($facture, $paiement);
        Carbon::setTestNow();
    }

    private function avatar(string $cle): ?string
    {
        $source = database_path('seeders/demo/avatars/'.$cle.'.webp');

        if (! is_file($source)) {
            return null;
        }

        $chemin = 'avatars/soukdemo-'.$cle.'.webp';
        Storage::disk('public')->put($chemin, (string) file_get_contents($source));

        return Storage::disk('public')->url($chemin);
    }

    private function capture(Appli $appli, string $cle): string
    {
        $source = database_path('seeders/demo/captures/'.$cle.'.webp');
        $chemin = 'captures/'.$appli->id.'/1.webp';
        Storage::disk('public')->put($chemin, is_file($source) ? (string) file_get_contents($source) : '');

        return Storage::disk('public')->url($chemin);
    }
}
