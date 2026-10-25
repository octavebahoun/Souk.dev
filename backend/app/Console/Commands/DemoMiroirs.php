<?php

namespace App\Console\Commands;

use App\Moteur\Commande;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Développement : fait des applis cobayes du jeu de démo (database/seeders/demo/depots) des
 * dépôts Git locaux, que le moteur clone à la place de github.com/soukdemo/… (MOTEUR_MIROIRS).
 */
class DemoMiroirs extends Command
{
    protected $signature = 'demo:miroirs';

    protected $description = 'Prépare les dépôts Git locaux des applis de démo (développement seulement)';

    public function handle(): int
    {
        $racine = config('moteur.miroirs');

        if (! app()->environment('local', 'testing') || ! is_string($racine) || $racine === '') {
            $this->warn('Miroirs ignorés : MOTEUR_MIROIRS absent ou environnement hors développement.');

            return self::SUCCESS;
        }

        foreach (File::directories(database_path('seeders/demo/depots')) as $source) {
            $nom = basename($source);
            $depot = rtrim($racine, '/').'/soukdemo/'.$nom.'.git';
            $travail = sys_get_temp_dir().'/soukdemo-'.$nom.'-'.bin2hex(random_bytes(4));

            File::deleteDirectory($depot);
            File::ensureDirectoryExists(dirname($depot));
            File::copyDirectory($source, $travail);

            $git = fn (array $arguments, ?string $dossier = null) => Commande::isolee([
                'GIT_CONFIG_GLOBAL' => '/dev/null',
                'GIT_CONFIG_NOSYSTEM' => '1',
                'GIT_AUTHOR_NAME' => 'Souk.dev démo', 'GIT_AUTHOR_EMAIL' => 'demo@souk.dev',
                'GIT_COMMITTER_NAME' => 'Souk.dev démo', 'GIT_COMMITTER_EMAIL' => 'demo@souk.dev',
            ])->path($dossier ?? $travail)->timeout(60)->run(['git', ...$arguments])->throw();

            $git(['init', '-q', '-b', 'main']);
            $git(['add', '-A']);
            $git(['commit', '-q', '-m', 'Appli de démo']);
            $git(['clone', '-q', '--bare', $travail, $depot], dirname($depot));
            File::deleteDirectory($travail);

            $this->info("Miroir prêt : soukdemo/{$nom}");
        }

        return self::SUCCESS;
    }
}
