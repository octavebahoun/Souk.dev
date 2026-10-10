<?php

namespace App\Console\Commands;

use App\Moteur\Exceptions\DepotInvalide;
use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\Exceptions\ManifestInvalide;
use App\Moteur\Exceptions\VariablesManquantes;
use App\Moteur\LimitesTaille;
use App\Moteur\MoteurDeploiement;
use Illuminate\Console\Command;
use Throwable;

class MoteurLancer extends Command
{
    protected $signature = 'moteur:lancer
                            {depot? : URL GitHub publique du dépôt}
                            {--chemin= : Chemin local, sans cloner}
                            {--copie= : Nom du projet Docker Compose}
                            {--taille=petite : Formule : petite (512 Mo, 0,5 cœur), moyenne (1 Go, 1 cœur) ou grande (2 Go, 2 cœurs)}
                            {--var=* : Variable client au format NOM=valeur}
                            {--dry-run : Valider et écrire le .env sans lancer Compose}';

    protected $description = 'Lance une copie isolée à partir d\'un dépôt GitHub ou d\'un chemin local';

    public function handle(MoteurDeploiement $moteur): int
    {
        $depot = $this->argument('depot');
        $chemin = $this->option('chemin');

        if (($depot === null || $depot === '') && ($chemin === null || $chemin === '')) {
            $this->error('Indiquez une URL GitHub ou --chemin.');

            return self::FAILURE;
        }

        if ($depot !== null && $depot !== '' && $chemin !== null && $chemin !== '') {
            $this->error('Indiquez un dépôt GitHub ou --chemin, pas les deux.');

            return self::FAILURE;
        }

        $taille = (string) $this->option('taille');

        if (! LimitesTaille::connue($taille)) {
            $this->error('La taille doit être petite, moyenne ou grande.');

            return self::FAILURE;
        }

        try {
            $variables = $this->variablesClient();
            $copie = $this->option('copie') ?: null;
            $dryRun = (bool) $this->option('dry-run');

            $resultat = $chemin
                ? $moteur->depuisChemin($chemin, $variables, $copie, $dryRun, $taille)
                : $moteur->depuisGithub($depot, $variables, $copie, $dryRun, $taille);
        } catch (ManifestInvalide|DepotInvalide|VariablesManquantes|LancementEchoue $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Le moteur a échoué : '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Copie « {$resultat->id} » prête.");
        $this->line('Répertoire : '.$resultat->repertoire);
        $this->line('Variables : '.implode(', ', array_keys($resultat->env)));

        if ($resultat->lancee) {
            $this->line('Service web : il répond.');
        } else {
            $this->comment('Dry-run : Docker Compose n\'a pas été lancé.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function variablesClient(): array
    {
        $paires = [];

        foreach ($this->option('var') as $entree) {
            if (! str_contains($entree, '=')) {
                throw new VariablesManquantes("Variable mal formée : {$entree}. Attendu : NOM=valeur.");
            }

            [$nom, $valeur] = explode('=', $entree, 2);

            if ($nom === '') {
                throw new VariablesManquantes("Variable mal formée : {$entree}. Attendu : NOM=valeur.");
            }

            $paires[$nom] = $valeur;
        }

        return $paires;
    }
}
