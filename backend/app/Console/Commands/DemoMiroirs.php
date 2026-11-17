<?php

namespace App\Console\Commands;

use App\Moteur\Commande;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Fait des applis cobayes du jeu de démo (database/seeders/demo/depots) des dépôts Git locaux, que
 * le moteur clone à la place de github.com/soukdemo/… (MOTEUR_MIROIRS) : en développement, et en
 * mode jury pour que chaque appli de démo se déploie vraiment. Chaque correctif proposé dans les
 * discussions de démo y a sa branche, pour que « Essayer ce correctif » lance une vraie copie.
 */
class DemoMiroirs extends Command
{
    protected $signature = 'demo:miroirs';

    protected $description = 'Prépare les dépôts Git locaux des applis de démo (développement ou mode jury)';

    /**
     * Branches des correctifs du jeu de démo (DemoSeeder::creerDiscussions) : la même appli, avec
     * un bandeau qui dit quel correctif tourne.
     *
     * @var array<string, array<string, string>>
     */
    private const CORRECTIFS = [
        'pharmastock' => [
            'correctif-callback-momo' => 'Correctif de Kofi : la référence MTN est lue dans le corps JSON du callback.',
            'idempotence-callback' => 'Correctif de Chinedu : un callback rejoué ne repaie jamais une commande.',
        ],
        'caisse-resto' => [
            'ticket-unique' => 'Correctif de Yao : un ticket n’est compté qu’une fois, même après un rafraîchissement.',
        ],
        'ecole-notes' => [
            'webhook-wave-brut' => 'Correctif de Fatou : la signature Wave est vérifiée sur le corps brut.',
        ],
    ];

    public function handle(): int
    {
        $racine = config('moteur.miroirs');

        $developpement = app()->environment('local', 'testing');

        if ((! $developpement && ! config('app.demo_jury')) || ! is_string($racine) || $racine === '') {
            $this->warn('Miroirs ignorés : MOTEUR_MIROIRS absent, ou ni développement ni mode jury (DEMO_JURY).');

            return self::SUCCESS;
        }

        foreach (File::directories(database_path('seeders/demo/depots')) as $source) {
            $nom = basename($source);

            // L'appli à la clé oubliée sert aux tests de la publication : jamais hors développement.
            if ($nom === 'fuite' && ! $developpement) {
                continue;
            }
            $depot = rtrim($racine, '/').'/soukdemo/'.$nom.'.git';
            $travail = sys_get_temp_dir().'/soukdemo-'.$nom.'-'.bin2hex(random_bytes(4));

            File::deleteDirectory($depot);
            File::ensureDirectoryExists(dirname($depot));
            File::copyDirectory($source, $travail);

            if ($nom === 'fuite') {
                $this->ajouterSecretFactice($travail);
            }

            $git = fn (array $arguments, ?string $dossier = null) => Commande::isolee([
                'GIT_CONFIG_GLOBAL' => '/dev/null',
                'GIT_CONFIG_NOSYSTEM' => '1',
                'GIT_AUTHOR_NAME' => 'Souk.dev démo', 'GIT_AUTHOR_EMAIL' => 'demo@souk.dev',
                'GIT_COMMITTER_NAME' => 'Souk.dev démo', 'GIT_COMMITTER_EMAIL' => 'demo@souk.dev',
            ])->path($dossier ?? $travail)->timeout(60)->run(['git', ...$arguments])->throw();

            $git(['init', '-q', '-b', 'main']);
            $git(['add', '-A']);
            $git(['commit', '-q', '-m', 'Appli de démo']);
            $this->ajouterVersions($nom, $travail, $git);
            $this->ajouterCorrectifs($nom, $travail, $git);
            $git(['clone', '-q', '--bare', $travail, $depot], dirname($depot));
            // Le moteur récupère une version par son commit (comme GitHub le permet) : le miroir l'accepte aussi.
            $git(['config', 'uploadpack.allowAnySHA1InWant', 'true'], $depot);
            File::deleteDirectory($travail);

            $this->info("Miroir prêt : soukdemo/{$nom}");
        }

        return self::SUCCESS;
    }

    /**
     * Branches des versions suivantes (database/seeders/demo/versions/{appli}) : chacune part d'une
     * autre et y pose ses fichiers. PharmaStock a une version 2 qui marche et une version cassée,
     * pour montrer une mise à jour réussie puis un retour arrière.
     */
    private function ajouterVersions(string $nom, string $travail, callable $git): void
    {
        $dossier = database_path('seeders/demo/versions/'.$nom);
        $liste = json_decode((string) @file_get_contents($dossier.'/versions.json'), true);
        if (! is_array($liste)) {
            return;
        }

        foreach ($liste as $version) {
            $git(['checkout', '-q', '-b', $version['branche'], $version['depuis']]);
            File::copyDirectory($dossier.'/'.$version['branche'], $travail);
            $git(['add', '-A']);
            $git(['commit', '-q', '-m', $version['message']]);
        }
        $git(['checkout', '-q', 'main']);
    }

    /** Une branche par correctif de démo : le site affiche, en haut, le correctif qui tourne. */
    private function ajouterCorrectifs(string $nom, string $travail, callable $git): void
    {
        foreach (self::CORRECTIFS[$nom] ?? [] as $branche => $texte) {
            $git(['checkout', '-q', '-b', $branche, 'main']);
            $page = $travail.'/site/index.html';

            if (is_file($page)) {
                $bandeau = '<div style="background:#ECFDF3;color:#067647;font:600 14px system-ui,sans-serif;padding:10px 16px;text-align:center;border-bottom:1px solid #ABEFC6">'
                    .'Version corrigée (branche '.e($branche).') : '.e($texte).'</div>';
                File::put($page, preg_replace('/<body([^>]*)>/', '<body$1>'.$bandeau, (string) File::get($page), 1));
            }

            $git(['add', '-A']);
            $git(['commit', '-q', '-m', $texte]);
            $git(['checkout', '-q', 'main']);
        }
    }

    /**
     * Une fausse clé de paiement, assemblée ici à chaque construction : aucun motif de clé
     * n'existe dans le dépôt de Souk.dev (ni pour gitleaks, ni pour le scan de GitHub).
     */
    private function ajouterSecretFactice(string $travail): void
    {
        $cle = 'sk'.'_'.'live'.'_'.'51Factice'.bin2hex(random_bytes(16));
        File::ensureDirectoryExists($travail.'/config');
        File::put($travail.'/config/paiement.js', "// Oubli volontaire du jeu de démo : la publication doit être refusée.\nexport const cle = '{$cle}'\n");
    }
}
