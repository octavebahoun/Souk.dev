<?php

namespace App\Console\Commands;

use App\Moteur\NettoyageCopies;
use Illuminate\Console\Command;

class MoteurNettoyer extends Command
{
    protected $signature = 'moteur:nettoyer
                            {--sans-orphelins : Ne pas supprimer les dossiers d{id} sans copie active}';

    protected $description = 'Arrête les copies de test expirées et supprime les dossiers de copie orphelins';

    public function handle(NettoyageCopies $nettoyage): int
    {
        $bilan = $nettoyage->executer(! $this->option('sans-orphelins'));

        $this->info("Copies expirées arrêtées : {$bilan['arretees']}. Échecs : {$bilan['ratees']}. Dossiers orphelins supprimés : {$bilan['orphelins']}.");

        return $bilan['ratees'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
