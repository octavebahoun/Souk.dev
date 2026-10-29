<?php

namespace App\Moteur;

use App\Models\Deploiement;
use App\Models\Machine;
use App\Moteur\Exceptions\LancementEchoue;
use Illuminate\Support\Facades\DB;

/**
 * Placement des copies : chaque nouvelle copie part sur la machine active et joignable qui a
 * le plus de mémoire libre. Une machine en maintenance (drain) ou injoignable ne reçoit rien ;
 * une machine sans limite connue passe devant (capacité inconnue, supposée libre).
 */
class PlacementMachines
{
    /**
     * Élit une machine et, quand la copie est donnée, y inscrit sa réservation dans la même
     * transaction : deux lancements simultanés ne prennent pas la même dernière place.
     */
    public function choisir(string $taille, ?Deploiement $deploiement = null): Machine
    {
        $plafond = LimitesTaille::plafondMo($taille);

        return DB::transaction(function () use ($plafond, $deploiement): Machine {
            Machine::assurerLocale();

            // Machines puis copies occupantes, toujours dans cet ordre et par id : le second
            // placement attend le premier et voit sa réservation.
            $machines = Machine::query()->orderBy('id')->lockForUpdate()->get();
            Deploiement::query()->whereIn('etat', Machine::ETATS_OCCUPES)->orderBy('id')->lockForUpdate()->get();

            $elue = null;
            $mieux = null;

            foreach ($machines as $machine) {
                if (! $machine->recoitEncoreDesCopies()) {
                    continue;
                }

                $libre = $machine->memoireLibreMo();

                if ($libre !== null && $libre < $plafond) {
                    continue;
                }

                // Sans limite connue, la machine passe devant : on ne refuse pas une copie
                // faute d'avoir déclaré une capacité.
                if ($elue === null || $libre === null || ($mieux !== null && $libre > $mieux)) {
                    $elue = $machine;
                    $mieux = $libre;
                }
            }

            if ($elue === null) {
                throw new LancementEchoue('Aucune machine ne peut accueillir cette copie pour l’instant : toutes sont pleines, en maintenance ou injoignables.');
            }

            $deploiement?->forceFill(['machine_id' => $elue->id])->save();

            return $elue;
        });
    }
}
