<?php

namespace App\Console\Commands;

use App\Http\Controllers\CopieTestController;
use App\Models\Deploiement;
use App\Moteur\DossierCopie;
use App\Moteur\NettoyeurCopie;
use Illuminate\Console\Command;

class MoteurNettoyer extends Command
{
    protected $signature = 'moteur:nettoyer
                            {--sans-orphelins : Ne pas supprimer les dossiers d{id} sans copie active}';

    protected $description = 'Arrête les copies de test expirées et supprime les dossiers de copie orphelins';

    public function handle(NettoyeurCopie $nettoyeur, DossierCopie $dossier): int
    {
        $arretees = 0;
        $ratees = 0;

        // Les copies en échec gardent expire_le : elles sont retentées au passage suivant.
        Deploiement::query()
            ->whereNotNull('expire_le')
            ->where('expire_le', '<=', now())
            ->whereIn('etat', [...CopieTestController::ETATS_ACTIFS, 'echec'])
            ->orderBy('id')
            ->each(function (Deploiement $deploiement) use ($nettoyeur, &$arretees, &$ratees) {
                $nettoyeur->arreter($deploiement) ? $arretees++ : $ratees++;
            });

        $orphelins = $this->option('sans-orphelins') ? 0 : $this->orphelins($nettoyeur, $dossier);

        $this->info("Copies expirées arrêtées : {$arretees}. Échecs : {$ratees}. Dossiers orphelins supprimés : {$orphelins}.");

        return $ratees === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function orphelins(NettoyeurCopie $nettoyeur, DossierCopie $dossier): int
    {
        $dossiers = $dossier->dossiersDeDeploiement();

        if ($dossiers === []) {
            return 0;
        }

        $actifs = Deploiement::query()
            ->whereIn('id', array_keys($dossiers))
            ->whereIn('etat', CopieTestController::ETATS_ACTIFS)
            ->pluck('id')
            ->all();

        $supprimes = 0;

        foreach ($dossiers as $id => $chemin) {
            if (in_array($id, $actifs, true)) {
                continue;
            }

            // down d'abord : un dossier orphelin peut encore avoir des conteneurs.
            if ($nettoyeur->liberer('d'.$id, $chemin, $id)) {
                $supprimes++;
            }
        }

        return $supprimes;
    }
}
