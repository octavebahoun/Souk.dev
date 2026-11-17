<?php

namespace App\Console\Commands;

use App\Models\Deploiement;
use App\Models\User;
use App\Moteur\NettoyeurCopie;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Données du jury (concours) : charge, ou recharge à neuf, le jeu de démo sur la plateforme en
 * ligne. Les copies que le jury a lancées avec les comptes de démo sont d'abord vraiment arrêtées
 * (conteneurs, volumes, dossier) : à lancer dans le conteneur « moteur », le seul qui joint Docker.
 * Les vrais comptes, leurs applis et leurs paiements ne sont jamais touchés.
 */
class DemoJury extends Command
{
    protected $signature = 'demo:jury';

    protected $description = 'Charge ou recharge les comptes et données de démo du jury (DEMO_JURY=true)';

    /** États où une copie occupe encore des conteneurs. */
    private const ETATS_ACTIFS = ['en_file', 'construction', 'demarrage', 'en_ligne', 'arret_en_cours', 'suspendu', 'mise_a_jour', 'echec'];

    public function handle(NettoyeurCopie $nettoyeur): int
    {
        if (! config('app.demo_jury') && ! app()->environment('local', 'testing')) {
            $this->error('Mode jury fermé : ajoutez DEMO_JURY=true dans backend/.env.prod, puis relancez la plateforme.');

            return self::FAILURE;
        }

        // Le moteur clone sous l'utilisateur propriétaire de /app : des miroirs créés en root lui
        // seraient refusés par Git (« dubious ownership »). docker compose exec entre en root.
        $proprietaire = @fileowner(base_path());
        if (function_exists('posix_geteuid') && posix_geteuid() === 0 && is_int($proprietaire) && $proprietaire !== 0) {
            $this->error('Lancez la commande sous l’utilisateur propriétaire de /app (uid '.$proprietaire.') : ./scripts/prod.sh exec -u '.$proprietaire.' moteur php artisan demo:jury');

            return self::FAILURE;
        }

        $copies = Deploiement::query()
            ->whereIn('user_id', User::query()->where('est_demo', true)->select('id'))
            ->whereIn('etat', self::ETATS_ACTIFS)
            ->whereNotNull('copie')
            ->get();

        foreach ($copies as $copie) {
            if (! $nettoyeur->arreter($copie)) {
                $this->error("La copie n° {$copie->id} ne s'arrête pas : rien n'est rechargé. Lancez la commande dans le conteneur « moteur ».");

                return self::FAILURE;
            }
        }

        if ($copies->isNotEmpty()) {
            $this->info("{$copies->count()} copie(s) de démo arrêtée(s).");
        }

        $this->call('echange:canaux');
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $comptes = User::query()->where('est_demo', true)->where('est_admin', false)->orderBy('id')->get();
        $this->table(['Compte', 'Nom', 'Profil'], $comptes->map(fn (User $compte) => [
            $compte->username,
            $compte->name,
            $compte->profil instanceof \BackedEnum ? $compte->profil->value : (string) $compte->profil,
        ])->all());
        $this->info('Les comptes s’ouvrent depuis la page de connexion du site (« Comptes de démonstration »).');

        return self::SUCCESS;
    }
}
