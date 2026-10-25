<?php

namespace App\Moteur;

use App\Http\Controllers\CopieTestController;
use App\Models\Deploiement;

/**
 * Passage planifié du moteur : arrête les copies de test expirées (et retente les
 * arrêts ratés), puis supprime les dossiers de copie sans déploiement actif.
 */
class NettoyageCopies
{
    public function __construct(
        private NettoyeurCopie $nettoyeur,
        private DossierCopie $dossier,
    ) {}

    /**
     * @return array{arretees: int, ratees: int, orphelins: int}
     */
    public function executer(bool $orphelins = true): array
    {
        $arretees = 0;
        $ratees = 0;

        // Les copies en échec gardent expire_le : elles sont retentées au passage suivant.
        Deploiement::query()
            ->whereNotNull('expire_le')
            ->where('expire_le', '<=', now())
            ->whereIn('etat', [...CopieTestController::ETATS_ACTIFS, 'echec'])
            ->orderBy('id')
            ->each(function (Deploiement $deploiement) use (&$arretees, &$ratees): void {
                $this->nettoyeur->arreter($deploiement) ? $arretees++ : $ratees++;
            });

        return [
            'arretees' => $arretees,
            'ratees' => $ratees,
            'orphelins' => $orphelins ? $this->orphelins() : 0,
        ];
    }

    private function orphelins(): int
    {
        $dossiers = $this->dossier->dossiersDeDeploiement();

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
            if ($this->nettoyeur->liberer('d'.$id, $chemin, $id)) {
                $supprimes++;
            }
        }

        return $supprimes;
    }
}
